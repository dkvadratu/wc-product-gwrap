jQuery(document).ready( function() {

});
	jQuery( document.body ).on( 'updated_cart_totals', function(){
		location.reload();
	});
	var ajaxUrl = "http://localhost/gwrap/wp-admin/admin-ajax.php";

	function add_wrap(data){
		var dataArr 				= data.split(',');
		var gwrap_product_id 		= dataArr[0];
		var gwrap_cart_key 			= dataArr[1];
		var gwrap_style_selected 	= jQuery('input[type=radio]:checked').val();

		var gwrap_style_qty 		= 1;
		gwrap_style_qty 			= document.getElementById("gwrap_qty_"+gwrap_product_id).value;

		jQuery.ajax({
			type: "POST",
			url:ajaxUrl,
			data: {action:"gift_wrap",func:"addWRAP",cartKEY:gwrap_cart_key,productID:gwrap_product_id,gwrapSTYLE:gwrap_style_selected,gwrapQTY:gwrap_style_qty}
	   })
	   .done(function(msg){
			console.log(msg);
	   });
	}

	function update_wrap(data){
		var dataArr 				= data.split(',');
		var gwrap_product_id 		= dataArr[0];
		var gwrap_cart_key 			= dataArr[1];

		var gwrap_style_selected 	= jQuery('input[type=radio]:checked').val();

		var gwrap_style_qty 		= 1;
		gwrap_style_qty 			= document.getElementById("gwrap_qty_"+gwrap_product_id).value;

			jQuery.ajax({
				type: "POST",
				url:ajaxUrl,
				data: {action:"gift_wrap",func:"updateWRAP",cartKEY:gwrap_cart_key,productID:gwrap_product_id,gwrapSTYLE:gwrap_style_selected,gwrapQTY:gwrap_style_qty}
		   })
		   .done(function(msg){
				console.log(msg);
		   });
	}

	function remove_wrap(data){
		var dataArr = data.split(',');
		var gwrap_product_id = dataArr[0];
		var gwrap_cart_key = dataArr[1];

			jQuery.ajax({
				type: "POST",
				url:ajaxUrl,
				data: {action:"gift_wrap",func:"removeWRAP",cartKEY:gwrap_cart_key,productID:gwrap_product_id}
		   })
		   .done(function(msg){
				console.log(msg);
		   });
	}
