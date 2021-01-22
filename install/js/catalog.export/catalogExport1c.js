var catalogExportApp = (function() {
    var resultTable = '<table id="result_table" cellpadding="0" cellspacing="0" border="0" width="100%" class="internal"><tbody>' +
                        '<tr class="heading"></tr>' +
                        '<tr><td><div id="status"></div></td></tr>' +
                        '<tr id="progress"><td height="10"><div style="border:1px solid #B9CBDF"><div id="indicator" style="height:10px; width:0%; background-color:#B9CBDF">' +
                        '</div></div></td><td width=30>&nbsp;<span id="percent">0%</span></td></tr>' + 
                        '<tr class="data"></tr></tbody></table>',                    

    startExport = function (e) {
        e.preventDefault();
        $('#result').html(resultTable);
        sendAjax();
    },
    
    workStartClick = function () {
        $("#work_start").on("click", startExport);  
    },

    showOrHideRecordsCount = function () {
        var packageUpload = $('#package_upload:checked').val();
        if (packageUpload) {
            $('#record_count_tr').show();
        } else {
            $('#record_count_tr').hide();
        }
    },

    packageUploadClickHandler = function() {
        $("#package_upload").on("click", showOrHideRecordsCount);
    },

    sendAjax = function (lastId = '') {
        var url = '/../bitrix/tools/custom.handlers/catalogExport1c.php';
        var filter = $('#1c_exchange_filter:checked').val(),
            recordCount = $('#record_count_filter').val(),
            packageUpload = $('#package_upload:checked').val();
        $.ajax({
            type: "GET",
            url: url,
            data: { 'WORK_START': 'Y', '1C_EXCHANGE_FILTER': filter, 'LAST_ID': lastId, 'PACKAGE_UPLOAD': packageUpload, 'RECORDS_COUNT_FILTER': recordCount },
            beforeSend: function(xhr) {
                ShowWaitWindow(); 
                $('#post_form input').prop('disabled', true);
                $('#status').html('Работаю...');
            },
            success: function(result) {
                console.log(result);
                data = JSON.parse(result);
                console.log(data);
                if (data.lastId) {
                    data.percent = data.percent > 0 ? data.percent : 0;
                    var percent = data.percent + '%';
                    $('#percent').html(percent);
                    $('#indicator').css("width", percent);
                    sendAjax(data.lastId);
                } else if (data.path) {
                    CloseWaitWindow();
                    $('#post_form input').prop('disabled', false);
                    $('#status').html('Экспорт завершен.');            
                    $("#result_table tbody").append('<tr><td><a href="' + data.path + '" target="_blank">Скачать архив</a></td></tr>');
                    $('#progress').hide();
                } else {
                    CloseWaitWindow();
                    $('#post_form input').prop('disabled', false);
                    $('#status').html('Нет новых данных.');   
                    $('#progress').hide();                
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log(jqXHR);
                console.log(textStatus);
                console.log(errorThrown);
                CloseWaitWindow();
                $('<tr><td>Возникла ошибка. Попробуйте позже</td></tr>').insertAfter("tr.data");                    
            }
        });    
    },
    
    init = function () {
        workStartClick();
        showOrHideRecordsCount();
        packageUploadClickHandler();
    };
    
    return {
        init: init
    };
    
})();

$(function() {
    catalogExportApp.init();
});