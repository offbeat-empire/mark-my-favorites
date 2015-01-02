var mmf = jQuery;
var ucc_mmf_ajax_request = null;

mmf(document).ready(function() {
	ucc_mmf_ajaxurl = ucc_mmf['ajaxurl'];

	mmf(document).on('click','.ucc-mmf-favorite', function(event) {
		event.preventDefault();

		inline = mmf(event.target).parent();
		object_id = inline.find('input.ucc-mmf-object-id').val();
		object_ref = inline.find('input.ucc-mmf-object-ref').val();
		nonce = inline.find('input.ucc-mmf-nonce').val();
		mode = inline.find('input.ucc-mmf-mode').val();

		if (ucc_mmf_ajax_request)
			ucc_mmf_ajax_request.abort();

		var data = {
			'action': 'ucc_mmf_favorite',
			'ucc_mmf_object_id': object_id,
			'ucc_mmf_object_ref': object_ref,
			'ucc_mmf_nonce': nonce,
			'ucc_mmf_mode': mode
		}

		ucc_mmf_ajax_request = mmf.post(ucc_mmf_ajaxurl, data, function(response) {
			var obj = mmf.parseJSON(response);
			var newform = obj.newform;

			inline.html(newform);
		});
		return false;
	});
});
