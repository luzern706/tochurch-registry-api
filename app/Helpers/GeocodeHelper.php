<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;

/**
 * 주소 → 좌표 변환 — 02_gh_admin_api CommonService::getCoordinatesFromAddress()와 동일 로직.
 * 카카오 로컬 검색 API 사용(.env KAKAO_REST_API_KEY 필요).
 */
class GeocodeHelper
{
    /**
     * @return array{latitude: float|null, longitude: float|null} 변환 실패 시 둘 다 null(예외 throw 안 함)
     */
    public static function getCoordinatesFromAddress(?string $address): array
    {
        $result = ['latitude' => null, 'longitude' => null];

        if (empty(trim((string) $address))) {
            return $result;
        }

        try {
            $kakaoApiKey = env('KAKAO_REST_API_KEY');
            if (!$kakaoApiKey) {
                LogHelper::logWrite("[GeocodeHelper] Kakao API Key is missing. (.env에 KAKAO_REST_API_KEY 설정 필요)", 'common');
                return $result;
            }

            $response = Http::withHeaders(['Authorization' => 'KakaoAK ' . $kakaoApiKey])
                ->timeout(30)
                ->connectTimeout(15)
                ->get('https://dapi.kakao.com/v2/local/search/address.json', [
                    'query' => trim((string) $address),
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data['documents'])) {
                    // 카카오 API: x = 경도(longitude), y = 위도(latitude)
                    $result['longitude'] = (float) $data['documents'][0]['x'];
                    $result['latitude']  = (float) $data['documents'][0]['y'];
                } else {
                    LogHelper::logWrite("[GeocodeHelper] Kakao Geocoding empty result for address: {$address}", 'common');
                }
            } else {
                LogHelper::logWrite("[GeocodeHelper] Kakao Geocoding HTTP failed: status={$response->status()}", 'common');
            }
        } catch (\Exception $e) {
            LogHelper::logWrite("[GeocodeHelper] Kakao Geocoding exception: " . $e->getMessage(), 'common');
        }

        return $result;
    }
}
