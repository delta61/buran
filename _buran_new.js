(function($){

	var CodeMirror_editor;
	
	$(document).ready(function(){
		$('.actform').each(function(){
			$(this).submit(function(){
				return false;
			});
		});

		if ($('.cdmrrinp').length) {
			$('.cdmrrinp').each(function(){
				CodeMirror_editor = CodeMirror.fromTextArea($(this)[0],{
					matchBrackets: true,
					lineWrapping: true,
					lineNumbers: true,
					tabSize: 4,
					indentUnit: 4,
					indentWithTabs: true,
					mode: "application/json",
					json: true,
					gutters: ["CodeMirror-lint-markers"],
					lint: true
				});
				CodeMirror_editor.on("renderLine",function(){
				});
			});
		}
	});
	
	$(document).on('click','.actform .sbmt',function(){
		if (CodeMirror_editor) {
			CodeMirror_editor.save();
		}
		var frm = $(this).parents('.actform');
		var prms = {
			uri: frm.attr('action'),
			reqdata: frm.serialize(),
		};
		$(window).trigger('actform_do',prms);
	});
	
	$(window).on('actform_do',function(e,prms){
		var datatype = prms.datatype ? prms.datatype : 'json';
		var getprms = prms.getprms ? prms.getprms : '';
		var reqdata = prms.reqdata ? prms.reqdata : false;
	
		var res = prms.resbox && prms.resbox.length
			? prms.resbox : $('.actform_res');
		var log = prms.logbox && prms.logbox.length
			? prms.logbox : $('.actform_log');
	
		res.addClass('pr');
	
		log.prepend('<div class="row"><div class="smplinfo"><span>Отправил запрос и жду ответ ...</span></div></div>');
		
		$.ajax({
			url: prms.uri+getprms,
			dataType: datatype,
			cache: false,
			data: reqdata,
		})
		.fail(function(){
			log.prepend('<div class="row"><div class="smplinfo"><span>Ошибка запроса! Попробуйте перезапустить.</span></div></div>');
		})
		.done(function(resdata){
			res.removeClass('pr');

			if (datatype != 'json') {
				log.html(resdata);
				return;
			}
			
			var tm = new Date(resdata.res.tm*1000).toISOString();
			tm = tm.replace(/^(\d+)-(\d+)-(\d+)T(\d+):(\d+):(\d+)(.*)/,'$3.$2.$1, $4:$5:$6');
			log.prepend('<div class="row"><div class="smplinfo cols"><span>Получил ответ</span><span>'+tm+'</span></div></div>');

			var row, compl, mresok, proc, mresitm;
			if (resdata.methods) {
				for (var mthd of Object.keys(resdata.methods)) {
					row = resdata.methods[mthd];

					mresok = row.res.ok == 'y' ? '' : 'ошибка';
					
					if (row.completed && row.completed == 'y') {
						compl = 'Завершено!';
					} else {
						compl = 'В процессе ...';

						resdata.nextact = true;
					}

					proc = 0;
					if (row.prgrsbr && row.prgrsbr.max && row.prgrsbr.max > 0) {
						proc = Math.round(row.prgrsbr.curr * 100 / row.prgrsbr.max);
					} else continue;

					mresitm = res.find('.mresitm_'+row.method);

					if (mresitm.length) {
						mresitm.find('.rs').text(mresok);
						mresitm.find('.cmpl').text(compl);
						mresitm.find('.prgrsbr .br').css('width',proc+'%');

					} else {
						res.prepend('<div class="row"><div class="mresitm mresitm_'+row.method+'"><div class="nm">'+row.mthd_nm+'</div><div class="rs">'+mresok+'</div><div class="cmpl">'+compl+'</div><div class="prgrsbr"><div class="br" style="width:'+proc+'%;"></div></div></div></div>');
					}
				}
			}

			if (resdata.res.errors && resdata.res.errors.length) {
				log.prepend('<div class="row"><div class="errstit"><span>Ошибки</span><span></span></div></div>');
				resdata.res.errors.forEach(function(er){
					log.prepend('<div class="row"><div class="erritm"><span></span><span>'+er.num+'</span></div></div>');
				});
			}

			if (prms.postproc) {
				resdata = prms.postproc(resdata, res, log);
			}

			if (resdata.nextact) {
				log.prepend('<div class="row"><div class="smplinfo"><span>Не закончил, продолжаю работу ...</span></div></div>');

				setTimeout(function(){
					$(window).trigger('actform_do',prms);
				},2000);
				return;

			} else {
				log.prepend('<div class="row"><div class="smplinfo"><span>Завершено!</span></div></div>');
			}
		});
	});
	
})(jQuery);
