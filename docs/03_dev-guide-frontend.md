# 프론트엔드 개발 가이드

> 대상 저장소: `04_gh_registry_front` (React)  
> API 서버: `04_gh_registry_api` (Laravel 10)

---

## 1. 로컬 개발 환경 세팅

### 1-1. 최초 세팅

```bash
# 저장소 클론
git clone <04_gh_registry_front 저장소 URL>
cd 04_gh_registry_front

# 패키지 설치
npm install
```

### 1-2. 환경 변수 파일 생성

프로젝트 루트에 `.env.local` 파일을 생성합니다.  
(`.env.local`은 `.gitignore`에 포함 — 커밋되지 않음)

```env
# 로컬 개발 시 API 서버 주소
VITE_API_BASE_URL=http://localhost:8000/api

# 또는 CRA 사용 시
REACT_APP_API_BASE_URL=http://localhost:8000/api
```

> **주의:** `.env.example` 또는 `.env.development`에 키 목록만 남기고 실제 값은 `.env.local`에만 작성합니다.

---

## 2. 로컬 디버깅 (개발 서버 실행)

### 2-1. API 서버 (Laravel) 먼저 실행

```bash
# 04_gh_registry_api 디렉토리에서
php artisan serve
# → http://localhost:8000 에서 실행
```

### 2-2. 프론트 개발 서버 실행

```bash
# Vite 기반 프로젝트
npm run dev
# → http://localhost:5173

# CRA(Create React App) 기반 프로젝트
npm start
# → http://localhost:3000
```

### 2-3. CORS 확인

Laravel `.env`에서 CORS 허용 도메인을 로컬 포트로 설정합니다.

```env
# .env (API 서버)
FRONTEND_URL=http://localhost:5173
```

`config/cors.php`에 `allowed_origins` 확인:

```php
'allowed_origins' => [env('FRONTEND_URL', 'http://localhost:3000')],
```

### 2-4. 브라우저 개발자 도구 활용

| 탭 | 활용 목적 |
|---|---|
| Network | API 요청/응답 확인, JWT 헤더 전송 여부 확인 |
| Console | 에러 메시지, API 응답 로그 |
| Application → LocalStorage | JWT 토큰 저장 상태 확인 |
| React DevTools (확장 설치) | 컴포넌트 상태, props 추적 |

---

## 3. API 연동 패턴

### 3-1. Axios 공통 설정 예시

```js
// src/lib/api.js
import axios from 'axios';

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL, // Vite
  // baseURL: process.env.REACT_APP_API_BASE_URL, // CRA
  headers: { 'Content-Type': 'application/json' },
});

// 요청 인터셉터 — JWT 자동 첨부
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token');
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

// 응답 인터셉터 — 401 처리
api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token');
      window.location.href = '/login';
    }
    return Promise.reject(err);
  }
);

export default api;
```

### 3-2. 로그인 후 토큰 저장

```js
const { data } = await api.post('/v4/auth/adminSignIn', { login_id, password });
if (data.status === 'success') {
  localStorage.setItem('token',      data.data.token);
  localStorage.setItem('admin_role', data.data.admin_role); // 'super' | 'admin'
  localStorage.setItem('church_id',  data.data.church_id);
}
```

### 3-3. 응답 구조

모든 API 응답은 아래 형식:

```json
{
  "status":  "success" | "fail",
  "code":    "" | "ERROR_CODE",
  "message": "" | "에러 메시지",
  "data":    { ... } | null
}
```

---

## 4. 빌드

### 4-1. 프로덕션 빌드

```bash
# Vite
npm run build
# → dist/ 폴더에 결과물 생성

# CRA
npm run build
# → build/ 폴더에 결과물 생성
```

### 4-2. 빌드 전 환경 변수 확인

```bash
# 운영 서버 API 주소로 설정
VITE_API_BASE_URL=https://api.yourdomain.com/api
```

`.env.production` 파일을 사용하거나 CI/CD 환경 변수로 주입합니다.

### 4-3. 빌드 결과 로컬 미리보기

```bash
# Vite
npm run preview
# → http://localhost:4173

# CRA (serve 패키지 필요)
npx serve -s build
```

---

## 5. 서버 배포

### 5-1. 빌드 파일 서버 전송 (SCP)

```bash
# dist/ 또는 build/ 폴더를 서버로 전송
scp -r dist/ user@서버IP:/var/www/html/registry

# 또는 rsync (변경분만 전송)
rsync -avz --delete dist/ user@서버IP:/var/www/html/registry
```

### 5-2. Nginx 설정 예시

```nginx
server {
    listen 80;
    server_name registry.yourdomain.com;
    root /var/www/html/registry;
    index index.html;

    # React Router (SPA) — 모든 경로를 index.html 로 포워딩
    location / {
        try_files $uri $uri/ /index.html;
    }

    # 빌드 파일 캐시 (JS, CSS, 이미지)
    location ~* \.(js|css|png|jpg|jpeg|svg|ico)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

> **SPA 라우팅**: `try_files $uri /index.html` 설정이 없으면 직접 URL 접근 시 404가 발생합니다.

### 5-3. 배포 후 확인 사항

- [ ] 브라우저에서 로그인 화면 접속 확인
- [ ] Network 탭에서 API 요청이 운영 서버로 향하는지 확인
- [ ] 401 처리 확인 (토큰 없이 접근 시 로그인 화면 리다이렉트)
- [ ] `admin_role=super` 계정으로 관리자 메뉴 노출 확인

---

## 6. 자주 발생하는 문제

| 증상 | 원인 | 해결 |
|---|---|---|
| CORS 오류 | API 서버 CORS 설정 누락 | `config/cors.php` 에 프론트 URL 추가 |
| 401 Unauthorized | JWT 만료 또는 토큰 미전송 | Authorization 헤더 확인, 토큰 재발급 |
| 빌드 후 새로고침 시 404 | Nginx SPA 설정 누락 | `try_files $uri /index.html` 추가 |
| API URL 오류 | 환경 변수 미적용 | `.env.production` 또는 빌드 시 환경 변수 확인 |
| 슈퍼 관리자 메뉴 안 보임 | `admin_role` 파싱 오류 | `localStorage.getItem('admin_role')` 값 확인 |

---

*최종 수정: 2026-06-20*
