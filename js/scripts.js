    jQuery(function(){
        jQuery('#ysw_migration_start').click(ysw_migration_batch);
        
        function ysw_migration_batch(){
            
            jQuery('#ysw_migration_working').fadeIn();
            jQuery('#ysw_migration_start').attr('disabled', 'disabled');
            jQuery.ajax({
                type: "POST",
                url: ajaxurl,
                dataType: 'json',
                data: 'action=ysw_migration_ajx',
                success: function(res){                            
                    jQuery('#ysw_migration_status:hidden').show();
                    jQuery('#ysw_migration_status').html(res.messages.join('<br />') + jQuery('#ysw_migration_status').html());
                    if(res.keepgoing){
                        qt_terms_keepgoing = 1;
                        // qt_import_terms_batch();
                    }else{
                        jQuery('#ysw_migration_working').fadeOut();
                        qt_keepgoing = 0;
                        // qt_import_process_batch();
                    }
                }
                                    
            })                        
        }
        
    })