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
		cache: false,
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
			if (bounce) { $("#"+s).show('fast', function() { document.body.ClassName = document.body.ClassName; }); }
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
	var checked=false;
	$('input:radio').each(function(){
		if ($(this).attr('checked')) {
			checked=true;
			var query= {
				sno:  $('#astribank').data('sno'),
				xpd:  $('#astribank').attr('data-xpd'),
			  	port: $('#astribank').data('port'),
				ext:  $('#extno').val(),
				did:  $('#didnum').val(),
				cid:  $('#cidnum').val(),
				cidname: $("#cidname").val(),
				tone:  $(this).attr('value'),
				'routes[]': [],
				action: 'addext'
			};
			$(":checked").each(function() {
  				query['routes[]'].push($(this).val());
			});
		$.post("ajax.php", query, function(data) { $("#addstat").html(data); });
		}
	});
	if (checked == false) {
		$("#addstat").html('<i>Please select a Dial Tone</i>');
	}
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
	$("#yesbutton").attr("disabled", true);
	$("#modext").attr("disabled", true);
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
	$('input:radio').each(function(){
		if ($(this).attr('checked')) {
			var query= {
				sno:  $('#astribank').data('sno'),
				xpd:  $('#astribank').attr('data-xpd'),
				port: $('#astribank').data('port'),
				ext:  $("#extno").val(),
				cidname: $("#cidname").val(),
				tone:  $(this).attr('value'),
				did:  $('#didnum').val(),
				cid:  $('#cidnum').val(),
				'routes[]': [],
				action: 'modify'
			};
			$(":checked").each(function() {
  				query['routes[]'].push($(this).val());
			});
			$.post("ajax.php", query, function(data) { 
				$("#ctext").html(data);
			});
		};
	});
	
}	

function blink(sno, cmd) {
	var query= {
		sno:  sno,
		action: cmd 
	}
        $.ajax({
                type: 'POST',
                url: 'ajax.php',
                data: query,
                beforeSend: function() {
                        $(".ports").hide("fast");
                        $("#olay").show();
                        $("#olay").spin("large", "white");
                },
                success: function( msg ) {
                        $("#olay").spin(false);
			$("#content").overlay({ 
				top: 160, 
				load: true, 
				mask: { color: '#fff', loadSpeed: 200, opacity: 0.5 },
				onClose: function() { 
					$("#olay").spin(false);
					$('#olay').hide(); 
				}
			});
			$("#content").overlay().load();
			$("#ctext").html(msg);
		}
	});
	return false;
}

function rpshowhide() {
	if ($('#routediv').is(":visible")) {
		$('#routediv').hide();
	} else {
		$('#routediv').show();
	}
	return false;
}

function moveshowhide() {
	if ($('.mleft').is(":visible")) {
		$('.mleft').each(function() { $(this).hide(); });
	} else {
		$('.mleft').each(function() { $(this).show(); });
	}
	return false;
}

function resync() {
	$("#olay").show();
	$.get("ajax.php", 'action=resync', function(data) { 
		$("#ctext").html(data); 
		$("#content").overlay({ 
			top: 160, 
			load: true, 
			mask: { color: '#fff', loadSpeed: 200, opacity: 0.5 },
			onClose: function() { 
				$("#olay").hide(); 
			}
		});
		$("#content").overlay().load();
	});
	return false;
}

function doresync() {
	$(":button").each( function() { $(this).attr("disabled", true); });
	$("#content").spin("large", "black");
	$.get("ajax.php", 'action=doresync', function(data) { 
		$("#content").spin(false);
		$("#ctext").html(data); 
	});

}
	

function updatedid() {
	$("#didnum").val($("#didnum").attr('data-did')+$("#extno").val());
}
function updatecid() {
	$("#cidnum").val($("#cidnum").attr('data-cid')+$("#extno").val());
}
