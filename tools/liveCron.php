<?php
    if(isset($_SERVER['SERVER_PROTOCOL'])) die('invalid request');
    define('__ZBXE__', true);
    $path = str_replace('/modules/livexe/tools/liveCron.php','',__FILE__);
    require_once($path.'/config/config.inc.php');
    $oContext = &Context::getInstance();
    $oContext->init();
    $oLive = &getController('livexe');
    $status = $oLive->doCrawl(false);

    printf("\r\n");
    printf("- date : %s\r\n", date("Y/m/d H:i"));
    printf("- target rss url : %s\r\n", number_format($status['total_rss']));
    printf("- crawled items : %s\r\n", number_format($status['items']));
    printf("- crawled tags %s\r\n", number_format($status['tags']));
    printf("- crawled images %s\r\n", number_format($status['images']));
    printf("\r\n");
?>
