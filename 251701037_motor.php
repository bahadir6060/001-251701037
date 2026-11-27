<?php
declare(strict_types=1);

function adim(array $s, string $a): array {
    if ($s['bitti']) return $s;

   
    if (str_starts_with($a, 'T-')) {
        $parca = explode('-', $a);
        $urunKey = $parca[1] ?? '';
        $adet = (int)($parca[2] ?? 0);
        if (!isset($s['urunler'][$urunKey]) || $adet <= 0) return $s;

        $fiyat = $s['urunler'][$urunKey]['fiyat'];
        $tutar = $fiyat * 2 * $adet;
        if ($adet >= 5) {
            $tutar = (int)round($tutar * 0.8);
        }
        if ($s['kasa'] < $tutar) return $s;

        $s['kasa'] -= $tutar;
        $s['urunler'][$urunKey]['stok'] += $adet;
        $s['log'][] = "🚚 {$s['urunler'][$urunKey]['ad']} tedarik: {$adet} adet";
        return $s;
    }

   
    if ($a === 'G') {
        if ($s['gunluk_musteri'] < 5) return $s;

        $s['kasa'] -= (50 + 10 * $s['gun']);
        if ($s['gunluk_ret'] >= 2) {
            $s['memnuniyet'] = max(0, $s['memnuniyet'] - 5);
        }
        $s['urunler']['tost']['stok'] = (int)ceil($s['urunler']['tost']['stok'] / 2);
        $s['urunler']['hamburger']['stok'] = (int)ceil($s['urunler']['hamburger']['stok'] / 2);
        $s['log'][] = "🌙 Gün {$s['gun']} kapandı";

        $s['gunluk_musteri'] = 0;
        $s['gunluk_ret'] = 0;
        $s['seri'] = 0;
        $s['elektrik'] = 0;
        $s['yogunluk'] = false;

        if ($s['gun'] >= 5) {
            $s['bitti'] = true;
            $s['kazandi'] = ($s['kasa'] > 0 && $s['memnuniyet'] > 0);
        } else {
            $s['gun']++;
        }
        return $s;
    }

   
    if ($a === 'M') {
        if ($s['gunluk_musteri'] >= 5) return $s;

        $s['musteri_sira']++;
        $s['gunluk_musteri']++;
        $m = musteri($s['no'], $s['musteri_sira']);
        $uKey = $m['urun'];
        $u = &$s['urunler'][$uKey];

       
        $adet = $s['yogunluk'] ? 2 : 1;
        $s['yogunluk'] = false;

        
        $fiyat = $u['fiyat'];
        if ($m['tip'] === 'Burslu Öğrenci') {
            $fiyat = (int)round($fiyat * 0.95);
        }
        $toplamTutar = $fiyat * $adet;

       
        $satisEngel = false;
        if ($s['elektrik'] > 0) {
            $s['elektrik']--;
            $satisEngel = true;
        }

        if (!$satisEngel && $u['stok'] >= $adet && $m['butce'] >= $toplamTutar) {
            $u['stok'] -= $adet;
            $s['kasa'] += $toplamTutar;
            $s['memnuniyet'] = min(100, $s['memnuniyet'] + 2);
            $s['seri']++;

            if ($s['seri'] === 3) {
                $s['kasa'] += 10;
                $s['seri'] = 0;
            }

            if ($u['stok'] <= 3 && !$s['zam'][$uKey]) {
                $u['fiyat'] += 5;
                $s['zam'][$uKey] = true;
            }

            $s['log'][] = "👤 {$m['ad']} ({$m['tip']}, bütçe {$m['butce']} TL) {$u['ad']} istiyor → Satış: {$adet} adet";
        } else {
            if ($satisEngel) {
                $ceza = 8;
            } elseif ($u['stok'] < $adet) {
                $ceza = 4;
            } else {
                $ceza = 3;
            }

            $s['memnuniyet'] = max(0, $s['memnuniyet'] - $ceza);
            $s['seri'] = 0;
            $s['ret_sayac'][$uKey]++;
            $s['gunluk_ret']++;

            if ($s['ret_sayac'][$uKey] === 3) {
                $u['fiyat'] = max(5, $u['fiyat'] - 10);
                $s['ret_sayac'][$uKey] = 0;
            }

            $s['log'][] = "👤 {$m['ad']} ({$m['tip']}, bütçe {$m['butce']} TL) {$u['ad']} istiyor → Satış yok";
        }
        unset($u);

      
        $olay = olay($s['no'], $s['musteri_sira']);
        if ($olay === 'tedarik') {
            $maxKey = array_key_first($s['urunler']);
            foreach ($s['urunler'] as $k => $v) {
                if ($v['stok'] > $s['urunler'][$maxKey]['stok']) {
                    $maxKey = $k;
                }
            }
            $s['urunler'][$maxKey]['stok'] = max(0, $s['urunler'][$maxKey]['stok'] - 2);
            $s['log'][] = "📦 Tedarik sorunu";
        } elseif ($olay === 'elektrik') {
            $s['elektrik'] = 2;
            $s['log'][] = "⚡ Elektrik kesintisi";
        } elseif ($olay === 'yogunluk') {
            $s['yogunluk'] = true;
            $s['log'][] = "👥 Öğle yoğunluğu";
        } elseif ($olay === 'bahsis') {
            $s['kasa'] += 5;
            $s['log'][] = "😊 Memnun müşteri";
        }

        if ($s['kasa'] < 0 || $s['memnuniyet'] <= 0) {
            $s['bitti'] = true;
            $s['kazandi'] = false;
        }
        return $s;
    }

    return $s;
}
