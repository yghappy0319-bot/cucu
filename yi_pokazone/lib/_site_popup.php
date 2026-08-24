<?php
/**
 * 사이트 레이어 팝업 헬퍼
 */

if (!function_exists('site_popup_table_ready')) {
    function site_popup_table_ready(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        $ready = db_table_exists('tb_popup');
        return $ready;
    }
}

if (!function_exists('site_popup_sanitize_content')) {
    function site_popup_sanitize_content(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        // 관리자 입력: 기본 텍스트/줄바꿈 위주. 위험 태그 제거
        $html = strip_tags($html, '<br><b><strong><em><i><u><a><p><span>');
        // javascript: 링크 차단
        $html = preg_replace('/\shref\s*=\s*(["\'])\s*javascript:[^"\']*\1/iu', ' href="#"', $html) ?? $html;
        // 줄바꿈 → <br> (이미 태그 있는 경우에도 남은 개행 처리)
        $html = preg_replace("/\r\n|\r|\n/", '<br>', $html) ?? $html;
        return $html;
    }
}

if (!function_exists('site_popup_active_list')) {
    /**
     * @return list<array<string, mixed>>
     */
    function site_popup_active_list(string $page_key = ''): array
    {
        if (!site_popup_table_ready()) {
            return [];
        }

        $is_home = ($page_key === '' || $page_key === 'home' || $page_key === 'index');

        $where = [
            'pu_status = 1',
            '(pu_start_at IS NULL OR pu_start_at <= NOW())',
            '(pu_end_at IS NULL OR pu_end_at >= NOW())',
        ];
        if (!$is_home) {
            $where[] = "pu_target = 'all'";
        }

        $where_sql = implode(' AND ', $where);
        $rs = db_query("
            SELECT *
            FROM tb_popup
            WHERE {$where_sql}
            ORDER BY pu_sort ASC, pu_idx DESC
            LIMIT 10
        ");
        $rows = [];
        if (!$rs) {
            return $rows;
        }
        while ($r = db_assoc($rs)) {
            $rows[] = $r;
        }
        return $rows;
    }
}
