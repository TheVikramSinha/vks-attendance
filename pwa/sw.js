/**
 * VKS ATTENDANCE SYSTEM - SERVICE WORKER
 * 
 * Provides offline capability, caching, and push notifications
 * 
 * @version 1.0
 */

const CACHE_VERSION = 'vks-attendance-v1.0';
const BASE_PATH = '/vks/';

// Assets to cache on install
const CACHE_ASSETS = [
    BASE_PATH,
    BASE_PATH + 'public/css/main.css',
    BASE_PATH + 'public/js/app.js',
    BASE_PATH + 'auth/login',
    BASE_PATH + 'user/dashboard',
    BASE_PATH + 'offline.html'
];

// Install event - cache assets
self.addEventListener('install', (event) => {
    console.log('[SW] Installing Service Worker...', event);
    
    event.waitUntil(
        caches.open(CACHE_VERSION)
            .then((cache) => {
                console.log('[SW] Caching app shell');
                return cache.addAll(CACHE_ASSETS);
            })
            .then(() => {
                return self.skipWaiting();
            })
    );
});

// Activate event - clean up old caches
self.addEventListener('activate', (event) => {
    console.log('[SW] Activating Service Worker...', event);
    
    event.waitUntil(
        caches.keys()
            .then((cacheNames) => {
                return Promise.all(
                    cacheNames.map((cacheName) => {
                        if (cacheName !== CACHE_VERSION) {
                            console.log('[SW] Removing old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                return self.clients.claim();
            })
    );
});

// Fetch event - serve from cache or network
self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip cross-origin requests
    if (url.origin !== location.origin) {
        return;
    }
    
    // API requests - network first, then cache
    if (request.url.includes('/api/') || request.method !== 'GET') {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    // Clone response before caching
                    const responseClone = response.clone();
                    
                    // Cache successful GET requests
                    if (request.method === 'GET' && response.status === 200) {
                        caches.open(CACHE_VERSION)
                            .then((cache) => {
                                cache.put(request, responseClone);
                            });
                    }
                    
                    return response;
                })
                .catch(() => {
                    // Return cached response if offline
                    return caches.match(request)
                        .then((cachedResponse) => {
                            if (cachedResponse) {
                                return cachedResponse;
                            }
                            
                            // Queue POST requests for later sync
                            if (request.method === 'POST') {
                                return queueRequest(request);
                            }
                            
                            // Return offline page
                            return caches.match(BASE_PATH + 'offline.html');
                        });
                })
        );
        return;
    }
    
    // Static assets - cache first, then network
    event.respondWith(
        caches.match(request)
            .then((cachedResponse) => {
                if (cachedResponse) {
                    return cachedResponse;
                }
                
                return fetch(request)
                    .then((response) => {
                        // Cache successful responses
                        if (response.status === 200) {
                            const responseClone = response.clone();
                            
                            caches.open(CACHE_VERSION)
                                .then((cache) => {
                                    cache.put(request, responseClone);
                                });
                        }
                        
                        return response;
                    })
                    .catch(() => {
                        // Return offline page for navigation requests
                        if (request.mode === 'navigate') {
                            return caches.match(BASE_PATH + 'offline.html');
                        }
                    });
            })
    );
});

// Background sync event - sync offline queue
self.addEventListener('sync', (event) => {
    console.log('[SW] Background sync:', event.tag);
    
    if (event.tag === 'sync-attendance') {
        event.waitUntil(syncOfflineQueue());
    }
});

// Push notification event
self.addEventListener('push', (event) => {
    console.log('[SW] Push notification received:', event);
    
    let notificationData = {
        title: 'VKS Attendance',
        body: 'You have a new notification',
        icon: BASE_PATH + 'public/assets/icon-192.png',
        badge: BASE_PATH + 'public/assets/icon-72.png',
        tag: 'vks-notification',
        requireInteraction: false
    };
    
    if (event.data) {
        try {
            const data = event.data.json();
            notificationData = {
                ...notificationData,
                ...data
            };
        } catch (e) {
            console.error('[SW] Error parsing push data:', e);
        }
    }
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title, {
            body: notificationData.body,
            icon: notificationData.icon,
            badge: notificationData.badge,
            tag: notificationData.tag,
            requireInteraction: notificationData.requireInteraction,
            data: notificationData.data || {}
        })
    );
});

// Notification click event
self.addEventListener('notificationclick', (event) => {
    console.log('[SW] Notification clicked:', event);
    
    event.notification.close();
    
    const urlToOpen = event.notification.data?.url || BASE_PATH + 'user/dashboard';
    
    event.waitUntil(
        clients.matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                // Focus existing window if available
                for (const client of clientList) {
                    if (client.url.includes(BASE_PATH) && 'focus' in client) {
                        client.navigate(urlToOpen);
                        return client.focus();
                    }
                }
                
                // Open new window
                if (clients.openWindow) {
                    return clients.openWindow(urlToOpen);
                }
            })
    );
});

/**
 * Queue request for background sync
 * 
 * @param {Request} request Request to queue
 * @returns {Response}
 */
async function queueRequest(request) {
    try {
        const db = await openDB();
        const requestData = {
            url: request.url,
            method: request.method,
            headers: Object.fromEntries(request.headers.entries()),
            body: await request.clone().text(),
            timestamp: Date.now()
        };
        
        await db.add('offline-queue', requestData);
        
        // Register background sync
        if ('sync' in self.registration) {
            await self.registration.sync.register('sync-attendance');
        }
        
        return new Response(
            JSON.stringify({ 
                success: true, 
                message: 'Request queued for sync',
                queued: true 
            }),
            {
                status: 202,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    } catch (error) {
        console.error('[SW] Error queuing request:', error);
        return new Response(
            JSON.stringify({ 
                success: false, 
                message: 'Failed to queue request' 
            }),
            {
                status: 500,
                headers: { 'Content-Type': 'application/json' }
            }
        );
    }
}

/**
 * Sync offline queue with server
 */
async function syncOfflineQueue() {
    try {
        const db = await openDB();
        const queue = await db.getAll('offline-queue');
        
        console.log('[SW] Syncing offline queue:', queue.length, 'items');
        
        for (const item of queue) {
            try {
                const response = await fetch(item.url, {
                    method: item.method,
                    headers: item.headers,
                    body: item.body
                });
                
                if (response.ok) {
                    // Remove from queue
                    await db.delete('offline-queue', item.id);
                    console.log('[SW] Synced:', item.url);
                }
            } catch (error) {
                console.error('[SW] Sync error for:', item.url, error);
            }
        }
        
        // Show notification if sync completed
        if (queue.length > 0) {
            self.registration.showNotification('Attendance Synced', {
                body: `${queue.length} pending action(s) have been synced.`,
                icon: BASE_PATH + 'public/assets/icon-192.png',
                tag: 'sync-complete'
            });
        }
    } catch (error) {
        console.error('[SW] Background sync failed:', error);
    }
}

/**
 * Open IndexedDB for offline queue
 * 
 * @returns {Promise<IDBDatabase>}
 */
function openDB() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open('vks-attendance-db', 1);
        
        request.onerror = () => reject(request.error);
        request.onsuccess = () => resolve(request.result);
        
        request.onupgradeneeded = (event) => {
            const db = event.target.result;
            
            if (!db.objectStoreNames.contains('offline-queue')) {
                const objectStore = db.createObjectStore('offline-queue', { 
                    keyPath: 'id', 
                    autoIncrement: true 
                });
                objectStore.createIndex('timestamp', 'timestamp', { unique: false });
            }
        };
    });
}

// Message event - handle messages from clients
self.addEventListener('message', (event) => {
    console.log('[SW] Message received:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'SYNC_NOW') {
        syncOfflineQueue();
    }
});
