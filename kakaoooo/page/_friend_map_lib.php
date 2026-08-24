<?php
/**
 * 친구 거주 지도 — 인증 · 지역 파싱 · SVG 좌표 매칭
 * URL: /page/friend_map.php
 *   - 회원 코드(지갑 쿠키 · ?code=)만 지도
 *   - 코드 없으면 본방 오픈채팅 안내
 */

if (!defined('FRIEND_MAP_COOKIE')) {
    define('FRIEND_MAP_COOKIE', 'wallet_code');
    define('FRIEND_MAP_COOKIE_TTL', 7 * 86400);
}

include_once __DIR__ . '/_room_gate.php';

/** 지도 실화면 허용: tb_member.code 가 있을 때만 */
function fm_내부열람허용($회원 = null) {
    return !empty($회원) && !empty($회원['code']);
}

function fm_본방주소() {
    return room_본방주소();
}

/** 코드 없는 외부 접속: 본방 오픈채팅 안내. 회원 목록·지도는 내려주지 않는다. */
function fm_외부화면_출력($hint = '지도를 보려면 가방에서 접속하세요') {
    room_외부화면_출력($hint);
}

function fm_코드후보($code = '') {
    $out = [];
    $push = static function ($v) use (&$out) {
        $v = trim((string)$v);
        if ($v !== '' && !in_array($v, $out, true)) {
            $out[] = $v;
        }
    };
    $push($code);
    $push($_GET['code'] ?? '');
    $push($_REQUEST['code'] ?? '');
    $push($_COOKIE[FRIEND_MAP_COOKIE] ?? '');
    if (!empty($GLOBALS['wallet_preauth']['code'])) {
        $push($GLOBALS['wallet_preauth']['code']);
    }
    return $out;
}

function fm_쿠키저장($code) {
    $code = trim((string)$code);
    if ($code === '') {
        return;
    }
    setcookie(FRIEND_MAP_COOKIE, $code, time() + FRIEND_MAP_COOKIE_TTL, '/', '', false, true);
}

function fm_auth($code = '') {
    foreach (fm_코드후보($code) as $c) {
        $esc = addslashes($c);
        $row = db_select("
            SELECT idx, name, code, regdate,
                CAST(IFNULL(point,0) AS CHAR) AS point,
                IFNULL(status, 0) AS status
            FROM tb_member
            WHERE code = '{$esc}'
            LIMIT 1
        ");
        if (empty($row['name'])) {
            continue;
        }
        if ((int)($row['status'] ?? 0) === 1) {
            continue;
        }
        fm_쿠키저장($c);
        return [
            'code'    => $c,
            'nick'    => trim((string)$row['name']),
            'idx'     => (int)($row['idx'] ?? 0),
            'point'   => (string)($row['point'] ?? '0'),
            'regdate' => (string)($row['regdate'] ?? ''),
        ];
    }
    return null;
}

/** 친구지도 이용까지 필요한 입장 경과 시간(시간) */
function fm_대기시간_시간() {
    return 24;
}

/** 입장(regdate) 후 대기시간이 안 지났으면 true */
function fm_입장대기_미충족($regdate) {
    $regdate = trim((string)$regdate);
    if ($regdate === '') {
        return true;
    }
    $가입시각 = strtotime($regdate);
    if ($가입시각 === false) {
        return true;
    }
    return $가입시각 > strtotime('-' . fm_대기시간_시간() . ' hours');
}

/** 이용 가능 시각 (m/d H:i), 없으면 빈 문자열 */
function fm_입장대기_가능시각($regdate) {
    $regdate = trim((string)$regdate);
    if ($regdate === '') {
        return '';
    }
    $가입시각 = strtotime($regdate);
    if ($가입시각 === false) {
        return '';
    }
    return date('m/d H:i', $가입시각 + fm_대기시간_시간() * 3600);
}

/**
 * 남은 대기 시간 문구 (예: 12시간 34분)
 * @return string
 */
function fm_입장대기_남은문구($regdate) {
    $regdate = trim((string)$regdate);
    if ($regdate === '') {
        return fm_대기시간_시간() . '시간';
    }
    $가입시각 = strtotime($regdate);
    if ($가입시각 === false) {
        return fm_대기시간_시간() . '시간';
    }
    $가능 = $가입시각 + fm_대기시간_시간() * 3600;
    $remain = $가능 - time();
    if ($remain <= 0) {
        return '곧';
    }
    $h = (int)floor($remain / 3600);
    $m = (int)floor(($remain % 3600) / 60);
    if ($h > 0 && $m > 0) {
        return "{$h}시간 {$m}분";
    }
    if ($h > 0) {
        return "{$h}시간";
    }
    if ($m > 0) {
        return "{$m}분";
    }
    return '1분 미만';
}

/** 열람 가능 시각 unix timestamp (불가 시 0) */
function fm_입장대기_언락타임스탬프($regdate) {
    $regdate = trim((string)$regdate);
    if ($regdate === '') {
        return 0;
    }
    $가입시각 = strtotime($regdate);
    if ($가입시각 === false) {
        return 0;
    }
    return $가입시각 + fm_대기시간_시간() * 3600;
}

/** 경고창용 안내 문구 */
function fm_입장대기_경고문구($regdate) {
    $대기 = fm_대기시간_시간();
    $남은 = fm_입장대기_남은문구($regdate);
    $가능 = fm_입장대기_가능시각($regdate);
    $msg = "친구 지도는 방에 들어온 지 {$대기}시간이 지나야 이용할 수 있어요.\n\n남은 시간: {$남은}";
    if ($가능 !== '') {
        $msg .= "\n이용 가능: {$가능}";
    }
    return $msg;
}

/** 닉 기준 친구지도 이용 가능 여부 + regdate 조회 */
function fm_친구지도_상태($nick) {
    $nick = trim((string)$nick);
    $regdate = '';
    if ($nick !== '') {
        $행 = @db_select("SELECT regdate FROM tb_member WHERE name = '" . addslashes($nick) . "' LIMIT 1");
        $regdate = (string)($행['regdate'] ?? '');
    }
    return [
        'ok'      => !fm_입장대기_미충족($regdate),
        'regdate' => $regdate,
        'alert'   => fm_입장대기_경고문구($regdate),
    ];
}

/** 닉 기준 친구지도 이용 가능 여부 */
function fm_친구지도_이용가능($nick, $regdate = null) {
    if ($regdate !== null && $regdate !== '') {
        return !fm_입장대기_미충족($regdate);
    }
    return !empty(fm_친구지도_상태($nick)['ok']);
}

function fm_code_query($code) {
    $code = trim((string)$code);
    return $code !== '' ? ('?code=' . rawurlencode($code)) : '';
}

/** content 2번째 줄에서 지역(시•군) 추출 */
function fm_지역추출($content) {
    $content = (string)$content;
    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
    $second = trim((string)($lines[1] ?? ''));
    if ($second === '') {
        // 한 줄에 붙은 경우 대응
        if (preg_match('/[🥕🍭❄️🤍🌸]?\s*지역\(시[•·･]?군\)\s*:\s*(.+)/u', $content, $m)) {
            return trim($m[1]);
        }
        return '';
    }
    $사는곳 = preg_replace('/^[🥕🍭❄️🤍🌸]?\s*지역\(시[•·･]?군\)\s*:\s*/u', '', $second);
    return trim((string)$사는곳);
}

/**
 * 동의어·세부지역 → 대표 시·군 라벨
 * @return array{key:string,label:string}|null
 */
function fm_지역정규화($key) {
    static $map = [
        // 서울
        '서울특별시' => '서울', '수도권' => '서울',
        '강남' => '서울', '강서' => '서울', '송파' => '서울',
        '마포' => '서울', '영등포' => '서울', '노원' => '서울',
        // 인천
        '인천광역시' => '인천', '연수' => '인천', '부평' => '인천', '남동' => '인천',
        // 경기 세부 → 시
        '일산' => '고양', '덕양' => '고양',
        '분당' => '성남', '판교' => '성남', '수정' => '성남', '중원' => '성남',
        '동탄' => '화성', '병점' => '화성',
        '수지' => '용인', '기흥' => '용인', '죽전' => '용인', '처인' => '용인',
        '영통' => '수원', '팔달' => '수원', '장안' => '수원', '권선' => '수원',
        '경기광주' => '광주(경기)',
        '경기도' => '경기', '경기' => '경기',
        // 광역
        '부산광역시' => '부산', '해운대' => '부산',
        '대구광역시' => '대구', '대전광역시' => '대전',
        '광주광역시' => '광주', '울산광역시' => '울산',
        '세종특별자치시' => '세종',
        '제주특별자치도' => '제주', '제주도' => '제주', '제주시' => '제주',
        '강원특별자치도' => '강원', '강원도' => '강원',
        '충청북도' => '충북', '충청남도' => '충남',
        '전북특별자치도' => '전북', '전라북도' => '전북',
        '전라남도' => '전남', '경상북도' => '경북', '경상남도' => '경남',
    ];
    if (isset($map[$key])) {
        return ['key' => $map[$key], 'label' => $map[$key]];
    }
    return ['key' => $key, 'label' => $key];
}

/**
 * SVG viewBox 0 0 400 560 — 시·군 단위 좌표
 * @return array<int, array{key:string,x:float,y:float,label:string}>
 */
function fm_지역좌표표() {
    static $list = null;
    if ($list !== null) {
        return $list;
    }
    $raw = [
        // 서울
        ['서울특별시', 118, 121], ['서울', 118, 121],
        ['강남', 125, 126], ['강서', 104, 123], ['송파', 131, 126],
        ['마포', 110, 121], ['영등포', 109, 125], ['노원', 126, 113],
        ['수도권', 118, 121],
        // 인천
        ['인천광역시', 89, 132], ['인천', 89, 132],
        ['연수', 87, 136], ['부평', 91, 127], ['남동', 92, 132],
        // 경기 북부 (시별)
        ['경기북부', 135, 88],
        ['고양', 103, 113], ['일산', 96, 113], ['덕양', 108, 110],
        ['의정부', 125, 105], ['남양주', 142, 115], ['파주', 95, 103],
        ['김포', 91, 117], ['양주', 128, 95], ['동두천', 132, 82],
        ['포천', 148, 88], ['연천', 125, 70], ['가평', 168, 100], ['구리', 138, 118],
        // 경기 남부 (시별)
        ['경기남부', 128, 168],
        ['수원', 123, 149], ['영통', 128, 152], ['팔달', 123, 149],
        ['성남', 134, 135], ['분당', 132, 138], ['판교', 130, 136],
        ['용인', 138, 151], ['수지', 135, 145], ['기흥', 140, 155], ['죽전', 136, 142],
        ['부천', 96, 127], ['안양', 116, 137], ['안산', 102, 144],
        ['화성', 102, 155], ['동탄', 115, 160], ['병점', 108, 158],
        ['평택', 132, 174], ['하남', 142, 124], ['광명', 105, 133],
        ['시흥', 100, 139], ['이천', 166, 149], ['경기광주', 146, 134],
        ['오산', 125, 162], ['군포', 112, 142], ['의왕', 120, 140], ['과천', 122, 132],
        ['여주', 178, 155], ['양평', 168, 128], ['안성', 148, 175],
        ['경기도', 128, 150], ['경기', 128, 150],
        // 강원
        ['강원특별자치도', 244, 100], ['강원도', 244, 100], ['강원', 244, 100],
        ['춘천', 196, 92], ['원주', 215, 142], ['강릉', 314, 104],
        ['속초', 285, 62], ['동해', 339, 125],
        // 충청
        ['세종특별자치시', 150, 222], ['세종', 150, 222],
        ['대전광역시', 160, 234], ['대전', 160, 234],
        ['충청북도', 192, 192], ['충북', 192, 192],
        ['청주', 171, 207], ['충주', 216, 174], ['제천', 243, 161],
        ['충청남도', 99, 220], ['충남', 99, 220],
        ['천안', 132, 191], ['아산', 120, 193], ['당진', 82, 184],
        ['서산', 63, 193], ['보령', 80, 235], ['논산', 130, 249], ['공주', 132, 225],
        // 전라
        ['전북특별자치도', 130, 294], ['전라북도', 130, 294], ['전북', 130, 294],
        ['전주', 135, 282], ['익산', 115, 271], ['군산', 93, 269], ['남원', 160, 320],
        ['광주광역시', 105, 344], ['광주', 105, 344],
        ['전라남도', 110, 377], ['전남', 110, 377],
        ['목포', 68, 372], ['여수', 189, 380], ['순천', 170, 363],
        ['광양', 192, 364], ['나주', 90, 357],
        // 경상
        ['대구광역시', 286, 278], ['대구', 286, 278],
        ['부산광역시', 335, 342], ['부산', 335, 342], ['해운대', 344, 343],
        ['울산광역시', 359, 309], ['울산', 359, 309],
        ['경상북도', 296, 229], ['경북', 296, 229],
        ['포항', 363, 264], ['경주', 350, 279], ['구미', 259, 255],
        ['김천', 235, 253], ['안동', 299, 214],
        ['경상남도', 255, 331], ['경남', 255, 331],
        ['창원', 294, 337], ['김해', 316, 337], ['양산', 331, 327],
        ['진주', 232, 342], ['거제', 288, 369], ['통영', 268, 372],
        // 제주
        ['제주특별자치도', 71, 497], ['제주도', 71, 497], ['제주시', 71, 497],
        ['서귀포', 74, 520], ['제주', 71, 497],
        ['호남', 110, 340], ['영남', 286, 303],
    ];

    $list = [];
    foreach ($raw as $row) {
        $list[] = [
            'key'   => $row[0],
            'x'     => (float)$row[1],
            'y'     => (float)$row[2],
            'label' => $row[0],
        ];
    }
    usort($list, static function ($a, $b) {
        return mb_strlen($b['key'], 'UTF-8') <=> mb_strlen($a['key'], 'UTF-8');
    });
    return $list;
}

/**
 * 대표 라벨의 표시 좌표 (정규화 후 핀 위치)
 * @return array{x:float,y:float}|null
 */
function fm_라벨좌표($label) {
    static $coords = [
        '서울' => [130, 118], '인천' => [58, 145],
        '고양' => [95, 88], '의정부' => [155, 78], '남양주' => [175, 105],
        '파주' => [72, 85], '김포' => [68, 112], '양주' => [148, 68],
        '동두천' => [160, 55], '포천' => [185, 72], '연천' => [140, 48],
        '가평' => [198, 95], '구리' => [160, 112],
        '수원' => [118, 175], '성남' => [158, 148], '용인' => [168, 178],
        '부천' => [78, 128], '안양' => [108, 155], '안산' => [82, 168],
        '화성' => [95, 192], '평택' => [128, 205], '하남' => [168, 128],
        '광명' => [95, 140], '시흥' => [70, 162], '이천' => [195, 165],
        '광주(경기)' => [178, 148], '오산' => [125, 188], '군포' => [105, 162],
        '의왕' => [122, 158], '과천' => [135, 138], '여주' => [205, 170],
        '양평' => [195, 130], '안성' => [160, 200],
        '경기북부' => [145, 70], '경기남부' => [130, 185], '경기' => [130, 160],
        '부산' => [335, 342], '대구' => [286, 278], '대전' => [168, 245],
        '광주' => [105, 344], '울산' => [359, 309], '세종' => [145, 220],
        '제주' => [71, 497], '서귀포' => [74, 520],
        '강원' => [244, 100], '충북' => [200, 185], '충남' => [90, 215],
        '전북' => [118, 300], '전남' => [110, 377], '경북' => [296, 229], '경남' => [255, 331],
        '춘천' => [196, 92], '원주' => [215, 142], '강릉' => [314, 104],
        '속초' => [285, 62], '동해' => [339, 125],
        '청주' => [178, 200], '충주' => [216, 174], '제천' => [243, 161],
        '천안' => [125, 198], '아산' => [108, 198],
        '전주' => [140, 275], '익산' => [115, 271], '군산' => [93, 269],
        '포항' => [363, 264], '경주' => [350, 279], '구미' => [259, 255],
        '창원' => [294, 337], '김해' => [316, 337],
    ];
    if (!isset($coords[$label])) {
        return null;
    }
    return ['x' => (float)$coords[$label][0], 'y' => (float)$coords[$label][1]];
}

/**
 * @return array{matched:bool,key:string,x:float,y:float,label:string}|null
 */
function fm_지역매칭($사는곳) {
    $사는곳 = trim((string)$사는곳);
    if ($사는곳 === '' || $사는곳 === '사는곳') {
        return null;
    }

    // 경기광주 vs 광주광역시
    $norm = preg_replace('/\s+/u', '', $사는곳);
    if (preg_match('/경기.?광주/u', $사는곳)
        || (mb_strpos($norm, '광주시', 0, 'UTF-8') !== false && mb_strpos($norm, '광주광역', 0, 'UTF-8') === false
            && mb_strpos($norm, '광주광역시', 0, 'UTF-8') === false)
    ) {
        // "광주시" alone without 광역시 → 경기 쪽으로 보는 건 위험. 경기광주만 확실할 때.
        if (preg_match('/경기.?광주/u', $사는곳)) {
            $c = fm_라벨좌표('광주(경기)');
            return [
                'matched' => true,
                'key'     => '광주(경기)',
                'x'       => $c['x'],
                'y'       => $c['y'],
                'label'   => '광주(경기)',
            ];
        }
    }

    $광역만 = ['경기도' => 1, '경기' => 1, '강원' => 1, '충북' => 1, '충남' => 1,
        '전북' => 1, '전남' => 1, '경북' => 1, '경남' => 1, '호남' => 1, '영남' => 1,
        '경기남부' => 1, '경기북부' => 1];

    $tryMatch = static function ($allowProvince) use ($사는곳, $norm, $광역만) {
        foreach (fm_지역좌표표() as $row) {
            $key = $row['key'];
            if (!$allowProvince && isset($광역만[$key])) {
                continue;
            }
            $keyNorm = preg_replace('/\s+/u', '', $key);
            if (mb_strpos($사는곳, $key, 0, 'UTF-8') === false
                && ($norm === '' || mb_strpos($norm, $keyNorm, 0, 'UTF-8') === false)
            ) {
                continue;
            }
            $normed = fm_지역정규화($key);
            $label = $normed['label'];
            $outKey = $normed['key'];
            $xy = fm_라벨좌표($label);
            return [
                'matched' => true,
                'key'     => $outKey,
                'x'       => $xy ? $xy['x'] : $row['x'],
                'y'       => $xy ? $xy['y'] : $row['y'],
                'label'   => $label,
            ];
        }
        return null;
    };

    // 1) 시·군 우선  2) 없으면 광역/남북부
    $hit = $tryMatch(false);
    if ($hit !== null) {
        return $hit;
    }
    return $tryMatch(true);
}

/**
 * status=0 회원 → 지도용 페이로드
 * @return array{pins:array,unknown:array,total:int,mapped:int}
 */
function fm_회원목록() {
    $rs = db_query("
        SELECT name, gender, content, title, level,
               CAST(IFNULL(point,0) AS CHAR) AS point,
               IFNULL(num, 0) AS num
        FROM tb_member
        WHERE IFNULL(status, 0) = 0
        ORDER BY name ASC
    ");

    $byRegion = []; // regionKey => members[]
    $unknown = [];
    $total = 0;

    while ($rs && ($row = db_fetch($rs))) {
        $total++;
        $name = trim((string)($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $content = (string)($row['content'] ?? '');
        $regionRaw = fm_지역추출($content);
        $pointStr = (string)($row['point'] ?? '0');
        $pointNeg = (isset($pointStr[0]) && $pointStr[0] === '-');

        $member = [
            'name'       => $name,
            'gender'     => (int)($row['gender'] ?? 0),
            'level'      => (int)($row['level'] ?? 0),
            'title'      => trim((string)($row['title'] ?? '')),
            'num'        => (int)($row['num'] ?? 0),
            'region'     => $regionRaw,
            'point_neg'  => $pointNeg,
            'content'    => $pointNeg ? '' : $content,
        ];

        $match = fm_지역매칭($regionRaw);
        if ($match === null) {
            $unknown[] = $member;
            continue;
        }
        $rk = $match['key'];
        if (!isset($byRegion[$rk])) {
            $byRegion[$rk] = [
                'key'   => $rk,
                'label' => $match['label'],
                'x'     => $match['x'],
                'y'     => $match['y'],
                'members' => [],
            ];
        }
        $byRegion[$rk]['members'][] = $member;
    }

    // 같은 지역 여러 명 → 살짝 흩뜨리기용 오프셋은 프론트에서 처리
    $pins = array_values($byRegion);
    return [
        'pins'    => $pins,
        'unknown' => $unknown,
        'total'   => $total,
        'mapped'  => $total - count($unknown),
    ];
}
