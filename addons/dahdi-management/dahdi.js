function bindall() {
	$('.click').unbind();
	$('.click').bind('click', function() {
		var s=$(this).data('sno');
		var x=$(this).attr('data-xpd'); // 00 = 0 if you use '.data'
		$('.spans').each( function() { $(this).css({"background-color": ''}); });
		$('#'+s+"_"+x).css({ "background-color": 'pink'});
		showports(s, x, true);
	});
};


function showports(s, x, bounce) {
	$.ajax({
		type: 'POST',
		url: 'ajax.php',
		data: 'action=ports&sno='+s+'&xpd='+x,
		beforeSend: function() {
			if (bounce) { $(".ports").hide("fast"); }
			$("#olay").show();
			$("#olay").spin("large", "white");
		},
		success: function( msg ) {  
			$("#olay").spin(false);
			$("#olay").hide();
			if (bounce) { $(".ports").hide('fast'); }
			$("#"+s).html(msg);
			if (bounce) { $("#"+s).show('fast'); }
			$(".ext").bind('click', function() { 
				modport($(this).data('sno'), $(this).attr('data-xpd'), $(this).data('portno'));
			});
		}
	});
}
function modport(ser, xpd, no) {
	$("#olay").show();
	$("#ctext").html("<i>Loading...</i>");
	$("#content").overlay({ 
		top: 160, 
		load: true, 
		mask: { color: '#fff', loadSpeed: 200, opacity: 0.5 },
		onClose: function() { 
			var query = {
				sno:  $('#astribank').data('sno'),
				xpd:  $('#astribank').attr('data-xpd'),
				action: 'span'
			};
			$.post("ajax.php", query, function(data) {
				$('#'+$('#astribank').data('sno')+'_'+$('#astribank').attr('data-xpd')).html(data);
				showports($('#astribank').data('sno'), $('#astribank').attr('data-xpd'), false);
				bindall();
			});
		}
	});
	$("#content").overlay().load();
	$.get("ajax.php", 'action=ext&sno='+ser+'&xpd='+xpd+'&port='+no, function(data) {
		$("#ctext").html(data);
	});	
}

function addext() {
	$("#addstat").html("<i>Processing...</i>");
	$('input:radio').each(function(){
		if ($(this).attr('checked')) {
			var query= {
				sno:  $('#astribank').data('sno'),
				xpd:  $('#astribank').attr('data-xpd'),
			  	port: $('#astribank').data('port'),
				ext:  $("#extno").val(),
				cidname: $("#cidname").val(),
				tone:  $(this).attr('value'),
				action: 'addext'
			};
		$.post("ajax.php", query, function(data) { $("#addstat").html(data); });
		}
	});
} 

function removeext() {
	var query= {
		sno:  $('#astribank').data('sno'),
		xpd:  $('#astribank').attr('data-xpd'),
		port: $('#astribank').data('port'),
		action: 'remove'
	};
	$.post("ajax.php", query, function(data) { $("#ctext").html(data); });
}

function doremoveext(ext) {
	$(":button").each(function() {
		$(this).attr("disabled", true);
	});
	$("#content").spin("large", "black");
	var query= {
		sno:  $('#astribank').data('sno'),
		xpd:  $('#astribank').attr('data-xpd'),
		ext:  ext,
		action: 'doremove'
	};
	$.post("ajax.php", query, function(data) { 
		$("#content").spin(false); 
		$("#ctext").html(data);
	});
}


function modext() {
	var query= {
		sno:  $('#astribank').data('sno'),
		xpd:  $('#astribank').attr('data-xpd'),
		ext:  ext,
		action: 'domodify'
	};
}	
