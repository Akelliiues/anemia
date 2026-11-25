<?php
declare(strict_types=1);
if (!defined('ANEMIA_STAFF_BOOT')) { http_response_code(404); exit; }

function anemiaSampleProvinces(array $provinces, bool $allEnabled = false): array
{
    $pending = ['54','58','64','61','16','19','71','77','24','45','42','48','31','36','35','82','85','94'];
    foreach ($provinces as &$province) $province['enabled'] = (int)($allEnabled || !in_array($province['code'],$pending,true));
    unset($province);
    return $provinces;
}

function anemiaSampleActivity(array $provinces, string $month): array
{
    $counts = array_fill_keys(array_keys(ANEMIA_ACTIVITY_LABELS),0);
    $outcomes = ['first'=>array_fill_keys(['low','medium','high','unknown'],0),
        'review_done'=>array_fill_keys(['low','medium','high','unknown'],0)];
    foreach ($provinces as $province) {
        $seed = crc32($province['code'] . ':' . $month);
        $start = 240 + $seed % 1450;
        $completed = (int)round($start * (.73 + ($seed % 12) / 100));
        $reviewed = (int)round($completed * .16);
        $events = ['start'=>$start,'first'=>$completed,'signal'=>(int)round($start*.09),
            'review_open'=>(int)round($completed*.2),'review_done'=>$reviewed,
            'review_skip'=>(int)round($completed*.025),'directory'=>(int)round($start*.12),
            'callback_open'=>(int)round($start*.055),'callback_sent'=>(int)round($start*.035)];
        for ($step=1;$step<=10;$step++) {
            $events['step_'.$step] = $completed + (int)round(($start-$completed)*(10-$step)/10);
        }
        foreach ($events as $key=>$value) $counts[$key] += $value;
        foreach (['first'=>[$completed,.58,.24,.12],'review_done'=>[$reviewed,.21,.44,.28]] as $key=>[$total,$low,$medium,$high]) {
            $bands = ['low'=>(int)round($total*$low),'medium'=>(int)round($total*$medium),'high'=>(int)round($total*$high)];
            $bands['unknown'] = $total-array_sum($bands);
            foreach ($bands as $band=>$value) $outcomes[$key][$band] += $value;
        }
    }
    return ['counts'=>$counts,'outcomes'=>$outcomes];
}

function anemiaSampleEvaluation(array $provinces, string $month, string $audience): array
{
    $report = ['overall'=>['n'=>0,'average'=>0,'satisfied'=>0],'domains'=>[],'topics'=>[]];
    foreach ($provinces as $province) {
        $seed = crc32($province['code'] . ':' . $month);
        foreach (EVALUATION_AUDIENCES as $group=>$_) {
            if ($audience !== '' && $audience !== $group) continue;
            $n = 20 + crc32($group . ':' . $seed) % 125;
            $report['overall']['n'] += $n;
            $report['overall']['average'] += $n * (3.9 + ($seed % 69) / 100);
            $report['overall']['satisfied'] += (int)round($n * (.72 + ($seed % 18) / 100));
            foreach (array_keys(EVALUATION_DOMAINS) as $index=>$key) {
                $rated = $n - (int)round($n * ($index + 1) / 25);
                $answers = 2 * $rated - (int)round($rated*.14);
                $report['domains'][$key] ??= ['respondents'=>0,'n'=>0,'average'=>0];
                $report['domains'][$key]['respondents'] += $rated;
                $report['domains'][$key]['n'] += $answers;
                $report['domains'][$key]['average'] += $answers * (3.82 + (($seed+$index*11) % 75) / 100);
            }
            foreach (array_keys(EVALUATION_TOPICS) as $index=>$key) {
                $report['topics'][$key] = ($report['topics'][$key] ?? 0) + (int)round($n*(.05+($seed+$index*3)%14/100));
            }
        }
    }
    $report['overall']['average'] /= max(1,$report['overall']['n']);
    $report['overall']['sd'] = $report['overall']['n'] > 1 ? 0.54 : null;
    foreach ($report['domains'] as $key=>&$domain) {
        $domain['average'] /= max(1,$domain['n']);
        $domain['sd'] = $domain['respondents'] > 1 ? 0.45 + (crc32($key) % 24)/100 : null;
    }
    unset($domain);
    return $report;
}
