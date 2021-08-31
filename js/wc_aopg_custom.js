jQuery( document ).on( 'change', '#reciept_image', function(){

    var fileName = jQuery(this).val();

    var fileData = new FormData();
    fileData.append('reciept_file', jQuery('#reciept_image').prop('files')[0]);
    fileData.append('security', jQuery('#security').val());
    fileData.append('action', 'store_image');

    if( fileName != "" ){
        jQuery.ajax({
            url: wc_aopg_js_obj.ajax_url,
            type:'POST',
            processData: false,
            contentType: false,
            dataType : 'json',
            data:  fileData,
            success : function( response ){
                console.log( response );
                if( response.result == "success" ){
                    jQuery( ".recieptImgError" ).remove();
                    jQuery( "#imgRecieptCont" ).attr( 'src',response.uploaded_image_url ).show();
                    jQuery( "#removeUploadedReciept" ).show();
                    jQuery( "#imgRecieptContUrl" ).attr( 'href',response.uploaded_image_url );
                }else{
                    jQuery( "#uploadStatus" ).html( 'Reciept uploading failed.' ).show();
                }
            }
        });
    }
});


// Check if reciept has been uploaded for Advacend Offline Payment method - 
jQuery( document ).on( 'click', '#place_order', function(){

    if( jQuery('#payment_method_offline_gateway').is(':checked') && jQuery('#reciept_image').prop('files').length == 0 ){
        jQuery('<span class="recieptImgError" style="color:red;">Please upload reciept image.</span>').appendTo(".place-order");
        return false;
    }else{
        jQuery(".recieptImgError").remove();
        return true;
    }

});

// Delete uploaded image 
jQuery( document ).on( 'click', '#removeUploadedReciept', function(){
    var recURL = jQuery( "#imgRecieptContUrl" ).attr( 'href' );
    

    var fileData1 = new FormData();
    fileData1.append('reciept_image', recURL);
    fileData1.append('security', jQuery('#security').val());
    fileData1.append('action', 'wc_aopg_delete_image');

    console.log(fileData1);

    if( recURL != "" ){
        jQuery.ajax({
            url: wc_aopg_js_obj.ajax_url,
            type:'POST',
            processData: false,
            contentType: false,
            dataType : 'json',
            data:  fileData1,
            success : function( response ){
                if( response.result == "success" ){
                    jQuery('#imageCont').remove();
                }else{
                    alert(response.response_status);
                }
            }
        });
    }
});
