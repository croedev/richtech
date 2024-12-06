const CACHE_VERSION = 'v1';
const STATIC_CACHE_NAME = `static-${CACHE_VERSION}`;

// 필수 캐시 파일만 남김
const STATIC_CACHE_URLS = [
  '/',
  '/index.html',
  '/manifest.json',
  '/css/style.css',
  '/js/main.js',
  '/assets/images/icon-192x192.png',
  '/assets/images/icon-512x512.png',
  '/offline.html'
];

// 설치 단계 - 캐시 등록
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(STATIC_CACHE_NAME)
      .then(cache => {
        console.log('[Service Worker] 캐싱 시작');
        return cache.addAll(STATIC_CACHE_URLS);
      })
      .then(() => {
        // 대기 중인 서비스 워커를 즉시 활성화
        return self.skipWaiting();
      })
  );
});

// 활성화 단계 - 이전 캐시 정리
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(cacheNames => {
        return Promise.all(
          cacheNames.map(cacheName => {
            if (cacheName !== STATIC_CACHE_NAME) {
              console.log('[Service Worker] 이전 캐시 삭제:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => {
        console.log('[Service Worker] 활성화 완료');
        // 활성화 즉시 모든 탭에 대해 제어권 획득
        return self.clients.claim();
      })
  );
});

// 네트워크 요청 처리
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request)
      .then(cachedResponse => {
        // 1. 캐시에서 찾기
        if (cachedResponse) {
          console.log('[Service Worker] 캐시에서 반환:', event.request.url);
          return cachedResponse;
        }

        // 2. 네트워크 요청
        return fetch(event.request)
          .then(response => {
            // 유효한 응답인지 확인
            if (!response || response.status !== 200 || response.type !== 'basic') {
              return response;
            }

            // 응답을 복제 (스트림은 한 번만 사용 가능)
            const responseToCache = response.clone();

            // 새로운 응답을 캐시에 저장
            caches.open(STATIC_CACHE_NAME)
              .then(cache => {
                cache.put(event.request, responseToCache);
                console.log('[Service Worker] 새 데이터 캐시:', event.request.url);
              });

            return response;
          })
          .catch(error => {
            console.log('[Service Worker] 오프라인 상태:', error);
            // 3. 오프라인이고 HTML을 요청한 경우
            if (event.request.mode === 'navigate') {
              return caches.match('/offline.html');
            }
            return null;
          });
      })
  );
});

// 푸시 알림 수신
self.addEventListener('push', event => {
  if (!event.data) return;

  const options = {
    body: event.data.text(),
    icon: '/assets/images/icon-192x192.png',
    badge: '/assets/images/badge.png',
    vibrate: [100, 50, 100],
    data: {
      dateOfArrival: Date.now(),
      primaryKey: '1'
    },
    actions: [
      {
        action: 'explore',
        title: '자세히 보기'
      },
      {
        action: 'close',
        title: '닫기'
      }
    ]
  };

  event.waitUntil(
    self.registration.showNotification('RichTech Club', options)
  );
});

// 푸시 알림 클릭 처리
self.addEventListener('notificationclick', event => {
  event.notification.close();

  if (event.action === 'explore') {
    clients.openWindow('/notifications');
  }
});

// 주기적 백그라운드 동기화
self.addEventListener('sync', event => {
  if (event.tag === 'sync-data') {
    event.waitUntil(
      // 오프라인 데이터 동기화 로직
      syncData()
    );
  }
});

// 오프라인 데이터 동기화 함수
async function syncData() {
  try {
    const offlineData = await getOfflineData();
    if (offlineData.length > 0) {
      // 서버에 데이터 전송
      await sendToServer(offlineData);
      // 성공적으로 전송된 데이터 삭제
      await clearOfflineData();
    }
  } catch (error) {
    console.error('[Service Worker] 동기화 실패:', error);
  }
} 