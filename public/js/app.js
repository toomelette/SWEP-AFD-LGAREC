

// LOADER
$('#loader')
    .hide() 
    .ajaxStart(function() {
        $(this).show();
    })
    .ajaxStop(function() {
        $(this).hide();
    })
;



// SELECT2 Caller
$('.select2').select2();



// Filter Form Submit Rule
$(document).ready(function($){
   $("#filter_form").submit(function() {
        $(this).find(":input").filter(function(){ return !this.value; }).attr("disabled", "disabled");
        return true;
    });
    $("form").find( ":input" ).prop( "disabled", false );
});



// WYSIHTML5
$(function () {
    CKEDITOR.replace('editor');
})



// Price Format
$(document).ready(function() {
    $(".priceformat").priceFormat({
        prefix: "",
        thousandsSeparator: ",",
        clearOnEmpty: true,
        allowNegative: true
    });
});




// PJAX Form Caller
$(document).on('submit', 'form[data-pjax]', function(event) {
 	$.pjax.submit(event, '#pjax-container');
});



// PJAX Link Caller
$(document).pjax('a[data-pjax]', '#pjax-container');




// PJAX INITIALIZATIONS
$(document).on('ready pjax:success', function() {
    
    //Filter Form Submit Rule
	$(document).ready(function($){
       $("#filter_form").submit(function() {
            $(this).find(":input").filter(function(){ return !this.value; }).attr("disabled", "disabled");
            return true;
        });
        $("form").find( ":input" ).prop( "disabled", false );
    });

});




// Input to Uppercase
$(document).on('blur', "input[data-transform=uppercase]", function () {
    $(this).val(function (_, val) {
        return val.toUpperCase();
    });
});




// iCheck for checkbox and radio inputs
$('input[type="checkbox"].minimal, input[type="radio"].minimal').iCheck({
  checkboxClass: 'icheckbox_minimal-blue',
  radioClass   : 'iradio_minimal-blue'
});



// Date Picker
$('.datepicker').each(function(){
    $(this).datepicker({
        autoclose: true,
        dateFormat: "mm/dd/yy",
        orientation: "bottom"
    });
});


// Table Rule
$(document).on('change', 'select[id="action"]', function () {
  var element = $(this).children('option:selected');
  if(element.data('type') == '1' ){ 
    location = element.data('url');
  }
});