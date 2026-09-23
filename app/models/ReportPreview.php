<?php
/** Paginate actual report values; never duplicate fixture pages or invent marks. */
class ReportPreview
{
    public static function pages(array $areas): array
    {
        $pages = [['legend'=>true,'columns'=>[[]]]];
        $page = 0; $column = 0; $used = 0;
        foreach ($areas as $area) {
            foreach ($area['subkategori'] as $group) {
                foreach ($group['item'] as $item) {
                    $cost = max(1, (int) ceil(mb_strlen($item['nama_tujuan']) / 29));
                    $limit = $page === 0 ? 22 : 31;
                    if ($used && $used + $cost + 3 > $limit) {
                        $column++;
                        if ($column > 1) { $page++; $column=0; $pages[$page]=['legend'=>false,'columns'=>[]]; }
                        $pages[$page]['columns'][$column]=[];
                        $used=0;
                    }
                    $sections = &$pages[$page]['columns'][$column];
                    $last = array_key_last($sections);
                    if ($last === null || $sections[$last]['title'] !== $area['nama_area']) {
                        $sections[]=['title'=>$area['nama_area'],'groups'=>[]]; $used+=2;
                        $last=array_key_last($sections);
                    }
                    $groups = &$sections[$last]['groups'];
                    $name = trim(($group['label'] ?? '') . '. ' . $group['nama']);
                    $g = array_key_last($groups);
                    if ($g === null || $groups[$g]['name'] !== $name) {
                        $groups[]=['name'=>$name,'items'=>[]]; $used++;
                        $g=array_key_last($groups);
                    }
                    $groups[$g]['items'][]=$item;
                    $used+=$cost;
                    unset($groups, $sections);
                }
            }
        }
        // Use the two-column paper layout even when data occupies only one column.
        foreach ($pages as &$p) if (count($p['columns']) === 1) $p['columns'][]=[];
        unset($p);
        return $pages;
    }
}
