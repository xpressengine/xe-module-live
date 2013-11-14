/**
 * @file   modules/livexe/js/livexe_admin.js
 * @author NHN (developers@xpressengine.com)
 * @brief  livexe 모듈의 관리자용 javascript
 **/


/* 모듈 생성 후 */
function completeInsertLivexe(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];

    var page = ret_obj['page'];
    var module_srl = ret_obj['module_srl'];

    alert(message);

    var url = current_url.setQuery('act','dispLivexeAdminInsert');
    if(module_srl) url = url.setQuery('module_srl',module_srl);
    if(page) url.setQuery('page',page);
    location.href = url;
}

/* 모듈 삭제 후 */
function completeDeleteLivexe(ret_obj) {
    var error = ret_obj['error'];
    var message = ret_obj['message'];
    var page = ret_obj['page'];
    alert(message);

    var url = current_url.setQuery('act','dispLivexeAdminIndex').setQuery('module_srl','');
    if(page) url = url.setQuery('page',page);
    location.href = url;
}

/* 일괄 설정 */
function doCartSetup(url) {
    var module_srl = new Array();
    jQuery('#fo_list input[name=cart]:checked').each(function() {
        module_srl[module_srl.length] = jQuery(this).val();
    });

    if(module_srl.length<1) return;

    url += "&module_srls="+module_srl.join(',');
    popopen(url,'modulesSetup');
}
