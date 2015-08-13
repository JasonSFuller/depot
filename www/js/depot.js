$(document).ready(function() {
	$(".my-filelist tbody tr").click(function() {
		var href = $(this).find(".my-filelink").attr("href");
		if (href) {
			window.location = href; 
		}
	});
});
