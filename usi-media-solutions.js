jQuery(document).ready(function($) {
   $('#usi_MM_library_page').click(
      function() { 
         var request = { 'action' : 'usi_action', 'usi_mm_option_active' : 'plus' };
            $.post(ajaxurl, request, function(response) {
            window.location = 'upload.php?page=usi_MM_library_page';
         });
      }
   );

   $('#usi_MM_library_standard_page').click(
      function() { 
         var request = { 'action' : 'usi_action', 'usi_mm_option_active' : 'standard' };
            $.post(ajaxurl, request, function(response) {
            window.location = 'upload.php';
         });
      }
   );
});