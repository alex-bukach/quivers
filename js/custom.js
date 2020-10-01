
  setTimeout(function(){
      jQuery('select').each(function( i ) {
        if(jQuery(this).attr('data-drupal-selector') =='claiming') {
          jQuery(this).addClass('claiming_group');
        }
      });
      jQuery(".claiming_group").select2({ placeholder: "- Select a Claiming Group -", allowClear: true });
    }, 1500);
