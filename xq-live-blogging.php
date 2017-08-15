<?php
/*
Plugin Name: XQ live blogging
Description: A simple live blogging plugin
Version: 20170813
Author: Xiaoqin Zhuang
*/

class XQ_Live_Blogging{
	static $instance = false;
	
	const KEY = '_lb';
	const KEY_ACTIVE = '_lb_active';
	const POST_TYPE = 'lb_entries';
	const POST_STATE = '[live]';
	
	public static function get_instance() {
		if ( !self::$instance ){
			self::$instance = new self;
		}
		return self::$instance;
	}
	
	public function __construct(){
		add_action('init', array($this, 'lb_init'));
		add_action('admin_menu', array( $this, 'lb_add_meta_boxes'));
		add_filter('the_title', array($this, 'lb_entry_content'), 10, 2);		
		
		// action to check checkbox in the front end
		add_action('admin_footer', array($this, 'lb_control_enable_live_blog'));
		
		// live post entry adding column
		add_filter('manage_lb_entries_posts_columns', array($this, 'lb_columns'));
		// live post entry displaying column
		add_action('manage_lb_entries_posts_custom_column',  array($this, 'lb_column_content'), 10, 2);		
		
		// actions to set live blog flag
		add_action('save_post', array($this, 'lb_enable_live_blog'));
		add_action('save_page', array($this, 'lb_enable_live_blog'));
		
		// action to post the entry
		add_action('save_post', array($this, 'lb_save_entry'));	
		
		// display the entries to live blog.
		add_action('wp_ajax_lb_update_live_blog', array( $this, 'lb_update_live_blog'));		
		// action to display live blog in the front end.
		add_action( 'template_redirect', array($this, 'lb_ajax_update_live_blog'));
		
		//add_filter( 'template_redirect', array( __CLASS__, 'lb_display_live_blog' ), 9 );
		// indicate live post
		add_filter( 'display_post_states', array( $this, 'add_display_post_state' ), 10, 2 );
	}	

	/**
	 * Live blogging init
	 */
	public function lb_init(){
		$labels = array(
			'name'					=> __( 'XQ Live Blog Entries', 'textdomain' ), 
			'singular_name'			=> __( 'Live Blog Entry', 'textdomain'),
			'add_new_item'         	=> __( 'Add New Entry', 'textdomain' ),
			'edit_item'				=> __( 'Edit Entry', 'textdomain' ),
			'view_item'				=> __( 'View Entry', 'textdomain' ),
			'all_items'				=> __( 'All Entries', 'textdomain'),
			'search_items'			=> __( 'Search Entries', 'textdomain' ),
			'not_found'             => __( 'No entries found.', 'textdomain' )			
		);
		
		register_post_type(self::POST_TYPE,
                       [
						   'labels'		 => $labels,
                           'public'      => true,
						   'show_ui'     => true,
						   'supports' 	 => array('title', 'editor', 'author'),
						   'publicly_queryable' => true
					]
		);
	}

	/**
	 * Adding all control meta boxes for live blogging.
	 */
	public function lb_add_meta_boxes(){
		add_meta_box(
			'enable_live_blogging',      // Unique ID
			esc_html__('Enable Live Blogging', 'textdomain'),    // Title
			array( $this, 'enable_lb_meta_box'),   // Callback function
			'page',         
			'side'         // Context
		);
		
		add_meta_box(
			'enable_live_blogging',      // Unique ID
			esc_html__( 'Enable live blogging', 'textdomain' ),    // Title
			array( $this, 'enable_lb_meta_box'),   // Callback function
			'post',         
			'side'         // Context
		);
		
		add_meta_box(
			'lb_entries',      // Unique ID
			esc_html__( 'Live blogs', 'textdomain' ),    // Title
			array( $this, 'lb_new_entry_meta_box'),   
			'lb_entries',         
			'side'        
		);		
		
		add_meta_box(
			'lb_entries_in_blog_meta_box',
			__('Entries', 'textdomain'),
			array( $this, 'lb_entries_in_blog_meta_box'),
			'page',
			'normal'
		);
	}	
		
	/**
	 * Display the option for user to set the post as live blog.
	 */
	public function enable_lb_meta_box(){
		wp_nonce_field('enable_lb_meta_box', 'lb_post_nonce');	
			
		global $post;
		$lb_enabled = false;
		if(get_post_meta($post->ID, self::KEY_ACTIVE, true)){
			$lb_enabled = true;
		}
		?>

		<p>
			<label for="lb-post-enable-box"><?php _e( "Enable live blogging", 'textdomain' ); ?></label>
			<br />
			<input type="checkbox" name="lb-post-enable-box" id="lb-post-enable-box" value="0" size="30" <?php if($lb_enabled) echo 'checked = checked'; ?>/>
		</p>
		<?php 
	}
	
		
	/**
	 * Display the content instead of title for entries.
	 */
	public function lb_entry_content($title, $id = 0)
	{
		$post = get_post($id);
		if ($id != 0 && self::POST_TYPE == $post->post_type)
		{
			$title = filter_var($post->post_content, FILTER_SANITIZE_STRING);
		}
		return $title;
	}
	
	/**
	 * Display content for new added column
	 */	 
	public function lb_column_content($column_name, $post_ID){
		global $post;		
		$page = get_page_by_title($post->post_title, 'OBJECT', 'post');
		switch ($column_name)
		{
			case 'lb':
			echo '<a href="post.php?post=' . $page->ID . '&amp;action=edit">' . $post->post_title . '</a>';

			break;
		}
	}
	
	/**
	 * Adding new column for showing live blog
	 */	
	public function lb_columns($columns){
		$columns['lb'] = __('Live Blog', 'live-blogging');
		return $columns;
	}
	
	/**
	 * Indicate in the post list that a post is a liveblog
	 *
	 */
	public function add_display_post_state( $post_states, $post = null ) {
		if ( is_null( $post ) ) {
			$post = get_post();
		}
		
		if ( $this->is_liveblog_post( $post->ID ) ) {
			$liveblog_state = $this->get_liveblog_state( $post->ID );
			if ( $liveblog_state ) {
				$post_states[] = __( self::POST_STATE, 'textdomain' );
			}
		}
		
		return $post_states;
	}
	
	/**
	 * Indicate if the blog is liveblog.
	 */
	public function is_liveblog_post($post_id){
		if(!get_post_meta($post_id, self::KEY, true)){
			return false;
		}
		
		return true;
	}	
	
	/**
	 * Indicate if the live blog is active.
	 */
	public function get_liveblog_state( $post_id = null ) {
		if (! is_single() && ! is_admin()) {
			return false;
		}
		if ( empty( $post_id ) ) {
			global $post;
			if ( ! $post ){
				return false;
			}
			$post_id = $post->ID;
		}
		
		$state = get_post_meta( $post_id, self::KEY_ACTIVE, true );

		return $state;
	}
	
	/**
	 * Display the side dropdown for selecting live blog to create entry.
	 */
	public function lb_new_entry_meta_box() {
		global $post;
		wp_nonce_field('lb_new_entry_meta_box', 'lb_post_nonce');
		
		$args = array(
				'meta_key' => self::KEY_ACTIVE,
				'meta_value' => '1',
				'post_type' => 'post',
				'posts_per_page' => -1,
				'orderby' => 'date',
				'order' => 'DESC'
		);
		
		$lblogs = array();
		// Query posts
		$q = new WP_Query($args);
		
		// Get all active live blogs
		while ($q->have_posts())
		{
			$q->next_post();
			$lblogs[$q->post->ID] = esc_attr($q->post->post_title);
		}		
		
		// Query pages
		$args['post_type'] = 'page';
		$q = new WP_Query($args);
		
		while ($q->have_posts())
		{
			$q->next_post();
			if(!get_liveblog_state($q->post->ID)){
				continue;
			}
			$lblogs[$q->post->ID] = esc_attr($q->post->post_title);
		}		
		?>
		
		<label for="lb_entry_post"><?php _e('Select live blog to post', 'textdomain' ); ?></label><br/>
		<select id="lb_entry_post" name="lb_entry_post">
		<?php foreach ($lblogs as $lbid => $lbname) { ?>
        <option value="<?php echo $lbid; ?>"><?php echo $lbname; ?></option>
		<?php } ?>
		</select>
  
		<?php		
	}	
	
	/**
	 * Method to save the entry post.
	 */
	public function lb_save_entry($post_id){
		// Check for autosaves
		if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) ||
			!isset($_POST['lb_entry_nonce']))
		{
			return $post_id;
		}
		
		// Verify nonce
		check_admin_referer('lb_save_entry', 'lb_entry_nonce');
		
		// Verify permissions
		if (!current_user_can('edit_post', $post_id))
		{
			return $post_id;
		}
		
		if (!isset($_POST['lb_entry_post']))
		{
			wp_delete_post($post_id, true);
			wp_die('<p>' . __('No active live blogs.', 'textdomain') . '</p>');
		}
		
		wp_set_post_terms($post_id, $_POST['lb_entry_post'], 'lb');
	}
	
	/**
	 * Action for ajax to update live blog entries.
	 * @return Objects of entry
	 */
	public function lb_update_live_blog() {
		$post_id = $_POST["post_id"];
		
		if(!get_post_meta($post_id, self::KEY, true)){
			wp_die;
		}
		
		$current_post = get_post($post_id);
		$current_post_title = $current_post->post_title;

		$args = array(
			'post_type' => self::POST_TYPE,
            'orderby' => 'date',
            'order' => 'DESC'
		);
		
		$liveblog = new WP_Query($args);		
		
		$response = array();
		$response_html = '';
        while ($liveblog->have_posts())
        {
			$liveblog->the_post();
			//Only display the entries in current post.
			if( $current_post_title == $liveblog->post->post_title){
				$author = get_author_name($liveblog->post->post_author);
				$date = $liveblog->post->post_date;
				$content = $liveblog->post->post_content;
				$post = array();
				$post['author'] = $author;
				$post['date'] = $date;
				$post['content'] = $content;
				
				array_push($response, $post);
				
				$response_html .= '<div class="lb-entry><div style="float: left;"><b>'.$author.'</b></div> 
					 <div style="float: right;  font-size: 12px; color: grey;">'.$date.'</div></div> 
					 <div style="clear: both; margin-bottom: 10px;">'.$content.'</div> \
					 <hr />';
			}
		}
		
		echo json_encode($response);
		wp_die();
	}
	
	/**
	 * Enable the live blogging flag depending on the metabox value.
	 */
	public function lb_enable_live_blog($post_id) {	  
		update_post_meta($post_id, self::KEY, '1');
		
		if (isset($_POST['lb-post-enable-box']) && 1 == $_POST['lb-post-enable-box']){
			update_post_meta($post_id, self::KEY_ACTIVE, '1');
		}
		else{
			update_post_meta($post_id, self::KEY_ACTIVE, '0');
		}
	}
	
	/***********************Front-end*****************************/
	
	/**
	 * Action to control live blog meta boxes behavior.
	 */
	public function lb_control_enable_live_blog(){
	?>
		<script type="text/javascript" >
		jQuery(document).ready(function() {			
			jQuery('#lb-post-enable-box').change(function(){
				if(jQuery('#lb-post-enable-box').is(':checked')){	
					jQuery('#lb-post-enable-box').val(1);
				}else{
					jQuery('#lb-post-enable-box').val(0);
				}				
			});
			
			if(jQuery.find('#lb_entry_post').length > 0){
				set_entry_title();
				jQuery('#lb_entry_post').change(function(){
					set_entry_title();
				});	
			}			
		});
		
		function set_entry_title(){
			var title = jQuery('#lb_entry_post option:selected').text();
			if(title !== null){
				jQuery('#title').val(title);
				jQuery('#title').focus();
			}
		}
		</script> 
	<?php
	}
	
	/**
	 * Function to update the live blog.
	 */
	public function lb_ajax_update_live_blog(){
		if(is_home()){
			return;
		}
		wp_reset_postdata();
	?>
		<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
		<script type="text/javascript" >
		jQuery(document).ready(function() {				
			lb_ajax_update_live_blog();					
		});
		
		function lb_ajax_update_live_blog(){
			var org_content = jQuery('.entry-content').html();			
			var entry_html = '';
			console.log(jQuery.find('.lb-entry'));
			jQuery('.lb-entry').remove();
			jQuery('.entry-content').closest(".lb-entry").remove();
			var data = {'action': 'lb_update_live_blog', 'post_id': <?php the_ID(); ?>};
			jQuery.post(
				"<?php echo admin_url('admin-ajax.php'); ?>",
				data,
				function(response) {												
					var posts = JSON.parse(response);
					console.log(response);
					
					jQuery.each(posts, function(index, value){
						//console.log(value['author']);
						entry_html += '<div class="lb-entry"><div style="float: left;"><b>'+ value['author'] + '</b></div> \
						 <div style="float: right;  font-size: 12px; color: grey;">' + value['date'] +'</div>\
						 <div style="clear: both; margin-bottom: 10px;">' + value['content'] + '</div> \
						 <hr /></div>';						
					});				
					
					jQuery('.entry-content').append(entry_html);
			});	
			setTimeout(lb_ajax_update_live_blog, 60000);		
			
		}
		</script>
	<?php
	}	
}

$xq_live_blogging = XQ_Live_Blogging::get_instance();