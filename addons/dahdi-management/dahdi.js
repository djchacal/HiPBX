function bindall() {
	$('.click').unbind();
	$('.click').bind('click', function() {
		var s=$(this).data('sno');
		var x=$(this).attr('data-xpd'); // 00 = 0 if you use '.data'
		showports(s, x);
	});
};


function showports(s, x) {
	$.ajax({
		type: 'POST',
		url: 'ajax.php',
		data: 'action=ports&sno='+s+'&xpd='+x,
		beforeSend: function() {
			$(".ports").hide("fast");
			$("#olay").show();
			$("#olay").spin("large", "white");
		},
		success: function( msg ) {  
			$("#olay").spin(false);
			$("#olay").hide();
			$(".ports").hide('fast');
			$("#"+s).html(msg);
			$("#"+s).show('fast');
			$(".ext").bind('click', function() { 
				modport($(this).data('sno'), $(this).attr('data-xpd'), $(this).data('portno'));
			});
		},
	});
}
function modport(ser, xpd, no) {
	$("#olay").show();
	$("#ctext").html("<i>Loading...</i>");
	$("#content").overlay({ 
		top: 160, 
		load: true, 
		mask: { color: '#fff', loadSpeed: 200, opacity: 0.5 },
		onBeforeClose: function() { 
			var query = {
				sno:  $('#astribank').data('sno'),
				xpd:  $('#astribank').attr('data-xpd'),
				action: 'span',
			};
			$.post("ajax.php", query, function(data) {
				$('#'+$('#astribank').data('sno')+'_'+$('#astribank').attr('data-xpd')).html(data);
				showports($('#astribank').data('sno'), $('#astribank').attr('data-xpd'));
				bindall();
			});
			$("#olay").hide(); },
	});
	$("#content").overlay().load();
	$.get("ajax.php", 'action=ext&sno='+ser+'&xpd='+xpd+'&port='+no, function(data) {
		$("#ctext").html(data);
		$("#addext").bind('click', function() {
			$('input:radio').each(function(){
				if ($(this).attr('checked')) {
					addext( $("#cidname").val(), $("#extno").val(), $(this).attr('value') );
				}
			});
		});	
	});
}

function addext(cidname, ext, tone) {
	$("#addstat").html("<i>Processing...</i>");
	var query= {
		sno:  $('#astribank').data('sno'),
		xpd:  $('#astribank').attr('data-xpd'),
	  	port: $('#astribank').data('port'),
		ext:  ext,
		cidname: cidname,
		tone: tone,
		action: 'addext',
	};
	$.post("doext.php", query, function(data) {
		$("#addstat").html(data);
	});
}
