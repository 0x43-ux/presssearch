<?php
// fetch_news.php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // CORS 설정 (필요에 따라 수정)

// CORS Preflight 요청 처리
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// JSON 입력 받기
$input = json_decode(file_get_contents('php://input'), true);
$queries = isset($input['queries']) ? $input['queries'] : [];
$from_time = isset($input['from_time']) ? trim($input['from_time']) : '';
$to_time = isset($input['to_time']) ? trim($input['to_time']) : '';

// 기본값 설정
if (empty($queries)) {
    $queries = ['한국농촌경제연구원'];
}

// 시간대 설정
date_default_timezone_set('Asia/Seoul'); // 시간대 설정

$current_time = time();
if (empty($to_time)) {
    $to_time = date('Y-m-d\TH:i', $current_time); // 현재 시점
}

if (empty($from_time)) {
    // 전날 오전 12시
    $from_time = date('Y-m-d\T00:00', strtotime('-1 day', $current_time));
}

// 시간대 필터링을 위한 타임스탬프 변환
$from_timestamp = strtotime($from_time);
$to_timestamp = strtotime($to_time);

// 네이버 API에 사용할 실제 시간은 UTC로 변환 (필요 시)
$from_time_utc = gmdate('Y-m-d\TH:i:s\Z', $from_timestamp);
$to_time_utc = gmdate('Y-m-d\TH:i:s\Z', $to_timestamp);

// 시간대 필터링을 위한 변환된 시간 사용
require_once '../config/config.php';

// 네이버 뉴스 API 엔드포인트
$api_url = 'https://openapi.naver.com/v1/search/news.json';

// 검색어를 AND 조건으로 결합 (Naver API는 AND 지원하지 않으므로, 대신 모든 검색어를 포함하도록 쿼리 작성)
$api_query = implode(' ', array_map(function($q) {
    return '"' . $q . '"';
}, $queries));

// API 요청 파라미터 설정
$params = http_build_query([
    'query' => $api_query,
    'display' => 100, // 최대 100개까지 요청 가능
    'start' => 1,
    'sort' => 'date'
]);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url . '?' . $params);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'X-Naver-Client-Id: ' . NAVER_CLIENT_ID,
    'X-Naver-Client-Secret: ' . NAVER_CLIENT_SECRET
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL Error: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

if ($http_code !== 200) {
    http_response_code($http_code);
    echo json_encode(['error' => 'Failed to fetch news from Naver API']);
    exit;
}

$data = json_decode($response, true);
$news_items = isset($data['items']) ? $data['items'] : [];

// 신문사 매핑 배열
$publisher_mapping = [
    'news2day.co.kr' => '뉴스투데이',
    'farminsight.net' => '팜인사이트',
    'danbinews.com' => '단비뉴스',
    'n.news.naver.com' => '네이버뉴스',
    'agrinet.co.kr' => '한국농어민신문',
    'insightkorea.co.kr' => '인사이트코리아',
    'news.lghellovision.net' => 'Hello Tv NEWS',
    'newsian.co.kr' => '뉴시안',
    'gokorea.kr' => '공감신문',
    'amnews.co.kr' => '농축유통신문',
    'ajunews.com' => '아주경제',
    'aflnews.co.kr' => '농수축산신문',
    'thepublic.kr' => '더퍼블릭',
    'newsworker.co.kr' =>'뉴스워커',
    'dynews.co.kr' => '동양일보',
    'ccdn.co.kr' => '충청매일',
    'jjn.co.kr' => '전북중앙',
    'datasom.co.kr' => '데이터솜',
    'ccdailynews.com' => '충청일보',
    'namdonews.com' => '남도일보',
    'farmnmarket.com' => '팜&마켓',
    'm.skyedaily.com'=> '스카이데일리',
    'ebn.co.kr' => 'EBN 산업경제',
    'goodmorningcc.com' => '굿모닝충청',
    'ibabynews.com' => '베이비뉴스',
    'ikpnews.net' => '한국농정',
    'knnews.co.kr' => '경남신문',
    'kwangju.co.kr' => '광주일보',
    'kbmaeil.com'=> '경북매일',
    'jnilbo.com' => '전남일보',
    'newsprime.co.kr' => '프라임경제',
    'megaeconomy.co.kr' => '메가경제',
    'joongdo.co.kr' => '중도일보', 
    'ngetnews.com' => '뉴스저널리즘',
    'smedaily.co.kr' => '중소기업신문',
    'biz.newdaily.co.kr' => '뉴데일리 경제',
    'newscj.com' => '천지일보',
    'pointdaily.co.kr' => '포인트데일리',
    'korea.kr' => '대한민국 정책브리핑',
    'chukkyung.co.kr'=> '축산경제신문'



];

// 시간대 필터링 및 검색어 포함 문장 추출
$filtered_news = [];
foreach ($news_items as $item) {
    // pubDate 예시: "Wed, 24 Dec 2024 10:00:00 +0900"
    $pub_date = strtotime($item['pubDate']);
    
    if ($pub_date < $from_timestamp || $pub_date > $to_timestamp) {
        continue; // 시간대 필터링
    }
    
    // HTML 태그 제거 및 엔티티 디코딩
    $title = html_entity_decode(strip_tags($item['title']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $description = html_entity_decode(strip_tags($item['description']), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // 문장 분리 (간단히 마침표, 물음표, 느낌표로 분리)
    $sentences = preg_split('/(?<=[.?!])\s+/', $description);
    
    // 모든 검색어 포함 문장 찾기
    $matching_sentences = [];
    foreach ($sentences as $sentence) {
        $all_keywords_present = true;
        foreach ($queries as $keyword) {
            if (mb_stripos($sentence, $keyword) === false) {
                $all_keywords_present = false;
                break;
            }
        }
        if ($all_keywords_present) {
            $matching_sentences[] = trim($sentence);
        }
    }
    
    // 모든 키워드 포함 문장이 없을 경우, 각 키워드가 포함된 문장 수집
    if (empty($matching_sentences)) {
        foreach ($sentences as $sentence) {
            foreach ($queries as $keyword) {
                if (mb_stripos($sentence, $keyword) !== false) {
                    $matching_sentences[] = trim($sentence);
                    break; // 동일 문장에 여러 키워드가 있을 경우 중복 추가 방지
                }
            }
        }
    }
    
    if (!empty($matching_sentences)) {
        // 발행일 및 시간 포맷팅 (예: 2024-12-24 10:00)
        $formatted_pub_date = date('Y-m-d H:i', $pub_date);
        
        // 신문사 추출 (링크의 도메인명 기반)
        $parsed_url = parse_url($item['link']);
        $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
        // www 제거
        $host = preg_replace('/^www\./', '', $host);
        // 도메인명만 추출 (예: example.com)
        $publisher = isset($publisher_mapping[$host]) ? $publisher_mapping[$host] : $host;
        
        // 뉴스 항목에 매칭된 문장과 발행일, 신문사 추가
        $filtered_news[] = [
            'title' => $title,
            'link' => $item['link'],
            'matching_sentences' => $matching_sentences, // 다중 매칭 문장 배열
            'pubDate' => $formatted_pub_date,
            'publisher' => $publisher
        ];
    }
}

echo json_encode($filtered_news);
?>
