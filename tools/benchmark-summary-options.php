<?php
// Synthetic CPU benchmark of the two loops in MI_Management_Service::summary().
// Excludes SQL, WordPress, network and cache: not an endpoint latency estimate.
function catalog(array $groups, bool $append): array {
    $options = [];
    foreach ($groups as $group) {
        if ($append) { foreach ($group as $option) $options[] = $option; }
        else $options = array_merge($options, $group);
    }
    $unique = [];
    foreach ($options as $option) $unique[$option['code']] = $option;
    return array_values($unique);
}
foreach ([100,1000,10000] as $count) {
    $groups=[];
    for($i=0;$i<$count;$i++) {
        $group=[];
        for($j=0;$j<10;$j++)$group[]=['code'=>'service-'.$j,'name'=>'Servizio sintetico '.$j,'price_cents'=>$i];
        $groups[]=$group;
    }
    if(catalog($groups,false)!==catalog($groups,true))throw new RuntimeException('Catalog changed');
    $times=[];
    foreach([false,true] as $append) {
        $runs=[];
        for($r=0;$r<5;$r++){$start=hrtime(true);catalog($groups,$append);$runs[]=(hrtime(true)-$start)/1e6;}
        sort($runs);$times[]=$runs[2];
    }
    printf("%d groups x 10: merge %.3f ms; append %.3f ms; equivalent catalog; median of 5\n",$count,$times[0],$times[1]);
}
