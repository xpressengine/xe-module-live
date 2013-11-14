function live_tab_show(tab,list,i){
    tab.parents('ul.liveTab').children('li.active').removeClass('active');
    tab.parent('li').addClass('active');
}

function completeGetRss(ret_obj) {
    var fo = jQuery('#insertRssForm')[0];
    fo.title.value = ret_obj['title'];
    fo.homepage.value = ret_obj['homepage'];
    fo.rss_url.value = ret_obj['rss_url'];

    jQuery('#getRssForm').css('display','none');
    jQuery('#insertRssForm').css('display','block');
}

function completeInsertRss(ret_obj) {
    location.reload();
}

function doCancelInsertRss() {
    var fo = jQuery('#insertRssForm')[0];
    fo.title.value = '';
    fo.homepage.value = '';
    fo.rss_url.value = '';
    jQuery('#getRssForm')[0].rss_url.value = '';

    jQuery('#getRssForm').css('display','block');
    jQuery('#insertRssForm').css('display','none');
}

function doDeleteRss(srl) {
    var params = new Array();
    params['mid'] = current_mid;
    params['livexe_rss_srl'] = srl;
    exec_xml('livexe', 'procLivexeDelete', params, function() { location.reload(); });
}
