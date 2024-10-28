<?php
/*
 * Plugin Name: Smart Excerpt
 * Description: This plugin aims to enhances WordPress' default excerpt generator thanks to smart algorithms analysing the content of your articles to extract the most relevant sentences of your articles.
 * Version: 1.4
 * Author: Cascio Calogero
 * Text Domain: Smart-excerpt
 * Domain Path: /lang
 * License: Dual licensed under the MIT and GPLv2 licenses
 *
 * Terms of use
 * ------------
 *
 * This software is copyright Cascio Calogero, and is distributed under the terms of the MIT and GPLv2 licenses.
 */

// If this file is called directly, abort.
if (! defined ( 'WPINC' ))
	die ();
	
	/*
 * Smart_excerpt
 *
 * @package Smart Excerpt
 *
 */
class Smart_excerpt {
	protected static $instance = NULL;
	public $pluginUrl = '';
	public $pluginPath = '';
	private $debug = FALSE;
	private $options = array ();
	private $sorted = array ();
	public static function get_instance() {
		NULL === self::$instance and self::$instance = new self ();
		return self::$instance;
	}
	
	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->pluginUrl = plugins_url ( '/', __FILE__ );
		$this->pluginPath = plugin_dir_path ( __FILE__ );
		$this->get_settings ();
		$this->sorted [] = null;
		add_action ( 'plugins_loaded', array (
				$this,
				'init' 
		) );
	}
	
	/**
	 * init()
	 */
	function init() {
		add_filter ( 'the_excerpt', array (
				$this,
				'my_excerpt' 
		), 1 );
	}
	
	/**
	 * Store options
	 */
	private function get_settings() {
		$this->options ["isActive"] = get_option ( 'isActive', FALSE );
		$this->options ["admin"] = get_option ( 'admin', FALSE );
		$this->options ["minLen"] = get_option ( 'minLen', 50 );
		$this->options ["maxLen"] = get_option ( 'maxLen', 150 );
		$this->options ["useTitle"] = get_option ( 'useTitle', 1 );
		$this->options ["useTags"] = get_option ( 'useTags', 1 );
		$this->options ["featured"] = get_option ( 'featured', FALSE );
		$this->options ["searchFeatured"] = get_option ( 'searchFeatured', FALSE );
		$this->options ["featuredSize"] = get_option ( 'featuredSize', 'thumbnail' );
		$this->options ["stripAll"] = get_option ( 'stripAll', true );
		$this->options ["stripTags"] = get_option ( 'stripTags', array (
				"img",
				"br",
				"p" 
		) );
		$this->options ["custTags"] = get_option ( 'custTags', '' );
		$this->options ["more"] = get_option ( 'more', FALSE );
		$this->options ["keepHtmlTags"] = get_option ( 'keepHtmlTags' );
		$this->options ["numSentences"] = get_option ( 'numSentences', 2 );
		$this->options ["skipManual"] = get_option ( 'skipManual', 0 );
		$this->options ["alignFeat"] = get_option ( 'alignFeat', 'alignleft' );
	}
	
	/**
	 * the_excerpt()
	 */
	public function my_excerpt() {
		global $post;
		if (! $this->options ["skipManual"] && '' != $post->post_excerpt) {
			echo $post->post_excerpt;
			return null;
		}
		$content = strip_shortcodes(get_the_content ());
		if ($this->debug)
			echo '-------<br>' . $content . '-------<br>';
		
		$content = $this->strip_the_tags ( $content );
		if ($this->debug)
			echo '-------<br>' . $content . '-------<br>';
			
			// Do not analyse short articles
		if (strlen ( $content ) < $this->options ["minLen"] or ! $this->options ["isActive"] or ($this->options ["admin"] and ! is_admin ())) { // Short article limit
			
			if ($this->options ["featured"] and has_post_thumbnail ()){
				echo get_the_post_thumbnail ( null, $this->options ["featuredSize"], array (
						'class' => $this->options ["alignFeat"] 
				) );
			} else if($this->options ["featured"] and $this->options ["searchFeatured"]) 
				echo '<a href="'.esc_url( get_permalink($post->ID)).'">
						<img src="' . $this->set_featured().'"></a>';
			
			$pos = @strrpos ( $content, " ", - ($content - $this->options ["maxLen"]) );
			$content = substr ( $content, 0, ($pos ? $pos : $this->options ["maxLen"]) ) . '…';
			echo $content = $this->enrich_the_excerpt ( $content );
			return null;
		}
		
		// Title based
		$dotNL = array (
				"…",
				" ...",
				". ",
				"? ",
				"! " 
		);
		$content = str_replace ( $dotNL, "||||", $content );
		$phrases = explode ( "||||", $content );
		$avg = (strlen ( $content ) / sizeof ( $phrases ));
		if ($this->debug)
			echo '-------<br>' . print_r ( $phrases );
		$tags = explode ( " ", trim ( strtolower ( get_the_title () ) ) );
		if (sizeof ( $tags ) > 0 && $tags [0] != '' && sizeof ( $phrases ) > 0 && $this->options ["useTitle"]) {
			$temp = $this->build_the_excerpt ( $tags, $phrases, "title", $avg );
		} else
			$tags = null;
			
			// Tag based
		$tags = get_the_tags (); // print_r($tags);
		if ($tags && $this->options ["useTags"] && sizeof ( $phrases ) > 0) {
			$temp = $this->build_the_excerpt ( $tags, $phrases, "tags", $avg );
		}
		
		// Personal tags
		$tags = explode ( ",", $this->options ["custTags"] );
		if (sizeof ( $tags ) > 0 && $tags [0] != '' && sizeof ( $phrases ) > 0) {
			$temp = $this->build_the_excerpt ( $tags, $phrases, "cust", $avg );
		}
		
		if ($temp) {
			if ($this->options ["featured"] and has_post_thumbnail ()){
				echo get_the_post_thumbnail ( null, $this->options ["featuredSize"], array (
						'class' => $this->options ["alignFeat"] 
				) );
			} else if($this->options ["featured"] and $this->options ["searchFeatured"]) 
				echo '<a href="'.esc_url( get_permalink($post->ID)).'">
						<img src="' . $this->set_featured().'"></a>';
			
			echo $this->enrich_the_excerpt ( $temp );
			return null;
		} else { // Short article
			if ($this->options ["featured"] and has_post_thumbnail ()){
				echo get_the_post_thumbnail ( null, $this->options ["featuredSize"], array (
						'class' => $this->options ["alignFeat"] 
				) );
			} else if($this->options ["featured"] and $this->options ["searchFeatured"]) 
				echo '<a href="'.esc_url( get_permalink($post->ID)).'">
						<img src="' . $this->set_featured().'"></a>';
			
			echo $this->strip_the_tags ( get_the_content () );
			
			return null;
		}
	}
	
	/**
	 * set_featured()
	 */
	private function set_featured() {
		global $post;

		$img = $this->extract_image ( $post );
		if (empty ( $img )) {
			return false;
		} else {
			return $img;
		}
	}
	
	/**
	 * Extracts the first image in the post content
	 */
	function extract_image($post) {
		$html = $post->post_content;
		if (stripos ( $html, '<img' ) !== false) {
			$regex = '#<\s*img [^\>]*src\s*=\s*(["\'])(.*?)\1#im';
			preg_match ( $regex, $html, $matches );
			unset ( $regex );
			unset ( $html );
			if (is_array ( $matches ) && ! empty ( $matches )) {
				return $matches [2];
			} else {
				return false;
			}
		} else {
			return false;
		}
	}
	
	/**
	 * strip_the_tags()
	 */
	private function strip_the_tags($content) {
		if ($this->options ["stripAll"])
			$content = preg_replace ( '/<[^>]*>/', " ", $content );
		else {
			$rem = array ();
			foreach ( $this->options ["stripTags"] as $tag )
				if ($tag == "img")
					array_push ( $rem, '/<img[^>]+\>/i' );
				else
					array_push ( $rem, '/\<' . $tag . '(\s*)?\/?\>/i' );
			
			$content = preg_replace ( $rem, "", $content );
		}
		
		return $content;
	}
	
	/**
	 * build_the_excerpt()
	 *
	 * @param string $tags,
	 *        	$phrases
	 * @return string $temp
	 *        
	 */
	private function build_the_excerpt(&$tags, $phrases, $options, $avg) {
		if ($options == "tags") {
			foreach ( $tags as $tag ) { // if($this->debug) echo $tag->slug;
				if (strlen ( trim ( $tag->slug ) ) > 2) {
					$i = 0;
					foreach ( $phrases as $phrase ) {
						$txt [$i] = $phrase;
						if (strlen($phrase)>0){
							$this->sorted [$i] += substr_count ( strtolower ( $phrase ), trim ( strtolower ( $tag->slug ) ) ) / (strlen ( $phrase ) * $avg);
							if ($this->debug)
								echo '4--------<br>' . $tag . '-<br>$i=' . $i . '<br>$this->sorted [$i]=' . $this->sorted [$i] . '<br>' . $phrase . '4--------<br>';
						
							$i ++;
						}
					}
				}
			} // print_r($txt);if($this->debug) echo '|||||';
		}
		if ($options == "title" and sizeof ( $tags ) > 0) {
			foreach ( $tags as $tag ) { // if($this->debug) echo '<p>tag:'. $tag.'</p>';
				if (strlen ( $tag ) > 2) {
					$i = 0; // if($this->debug) echo '<br>-'.'<br>-'.'<p>tag:'. $tag.'</p>';
					foreach ( $phrases as $phrase ) {
						
						$txt [$i] = $phrase;
						@$this->sorted [$i] += substr_count ( strtolower ( $phrase ), trim ( strtolower ( $tag ) ) ) / (strlen ( $phrase ) * $avg);
						if ($this->debug)
							echo '5--------<br>' . $tag . '<br>$i=' . $i . '<br>$this->sorted [$i]=' . $this->sorted [$i] . '<br>' . $phrase . '5--------<br>';
						
						$i ++;
					}
				}
			}
		} // print_r($txt);if($this->debug) echo '|||||';
		if ($options == "cust" and sizeof ( $tags ) > 0) {
			foreach ( $tags as $tag ) { // if($this->debug) echo '<p>tag:'. $tag.'</p>';
				if (strlen ( $tag ) > 2) {
					$i = 0; // if($this->debug) echo '<br>-'.'<br>-'.'<p>tag:'. $tag.'</p>';
					foreach ( $phrases as $phrase ) {
						
						$txt [$i] = $phrase;
						@$this->sorted [$i] += substr_count ( strtolower ( $phrase ), trim ( strtolower ( $tag ) ) ) / (strlen ( $phrase ) * $avg);
						if ($this->debug)
							echo '5--------<br>' . $tag . '<br>$i=' . $i . '<br>$this->sorted [$i]=' . $this->sorted [$i] . '<br>' . $phrase . '5--------<br>';
						
						$i ++;
					}
				}
			}
		} // print_r($txt);if($this->debug) echo '|||||';
		arsort ( $this->sorted, SORT_NUMERIC );
		foreach ( $this->sorted as $key => $val ) {
			if ($this->debug)
				echo '<p>' . $key . "=" . $val . '</p><br>a=' . $a . '<br>numS=' . $this->options ["numSentences"] . '<br>' . $txt [$key] . '<br>';
			$temp [$key] = (isset ( $txt [$key] ) ? @$txt [$key] . ". " : "");
		}
		ksort ( $temp );
		$text = ""; // print_r($temp);
		if (sizeof ( $temp ) > 0) {
			foreach ( $temp as $key => $val ) {
				$text .= $val;
				$txtLen = strlen ( preg_replace ( '/<[^>]*>/', " ", $text ) );
				if ($txtLen >= $this->options ["maxLen"]) {
					$text = substr ( $text, 0, strrpos ( $text, " ", - ($txtLen - $this->options ["maxLen"]) ) ) . '…';
					break;
				}
			}
			return $text;
		} else
			return false;
	}
	
	/**
	 * enrich_excerpt()
	 *
	 * @param string $temp        	
	 * @return string $temp
	 *        
	 */
	private function enrich_the_excerpt($temp) {
		$temp = force_balance_tags ( $temp );
		
		if (strlen ( trim ( $this->options ["more"] ) ) > 1)
			$temp .= "\n" . apply_filters ( 'the_content_more_link', '<a href="' . esc_url ( apply_filters ( 'the_permalink', get_permalink () ) ) . '" class="more-link">' . ($this->options ["more"]) . '</a>', null ) . "\n";
		
		return trim ( $temp ) . " ";
	}
}

$Smart_excerpt = Smart_excerpt::get_instance ();

/*
 * admin option
 */
if (is_admin ()) { // admin actions
	add_action ( 'admin_init', 'register_smaExc_settings' );
	add_action ( 'admin_menu', 'smaExc_register_options_page' );
}
function register_smaExc_settings() {
	register_setting ( 'smaExc-group', 'admin' );
	register_setting ( 'smaExc-group', 'isActive' );
	register_setting ( 'smaExc-group', 'minLen' );
	register_setting ( 'smaExc-group', 'maxLen' );
	register_setting ( 'smaExc-group', 'useTitle' );
	register_setting ( 'smaExc-group', 'useTags' );
	register_setting ( 'smaExc-group', 'featured' );
	register_setting ( 'smaExc-group', 'searchFeatured' );
	register_setting ( 'smaExc-group', 'featuredSize' );
	register_setting ( 'smaExc-group', 'more' );
	register_setting ( 'smaExc-group', 'numSentences' );
	register_setting ( 'smaExc-group', 'keepHtmlTags' );
	register_setting ( 'smaExc-group', 'stripAll' );
	register_setting ( 'smaExc-group', 'stripTags' );
	register_setting ( 'smaExc-group', 'custTags' );
	register_setting ( 'smaExc-group', 'skipManual' );
	register_setting ( 'smaExc-group', 'alignFeat' );
}

/*
 * Registers the option page
 */
function smaExc_register_options_page() {
	add_options_page ( 'Smart Excerpt', 'Smart Excerpt', 'manage_options', 'smaExc_plugin', 'smaExc_page' );
}

/*
 * Display the admin page
 */
function smaExc_page() {
	?>

<div class="wrap">
	<form method="post" action="options.php">
    <?php settings_fields( 'smaExc-group' ); ?>
    <table class="form-table">
			<tr>
				<td width="70%" valign="middle"><h1>A Smart Excerpt</h1></td>
				<td width="30%"><input type="checkbox" name="isActive" value="1"
					<?php if ( get_option('isActive') ) echo 'checked="checked"';  ?> />active<br> <input type="checkbox" name="admin"
					value="1" <?php if ( get_option('admin') ) echo 'checked="checked"';  ?> />only for admin
					<hr></td>
			</tr>
			<tr>
				<td width="70%" valign="middle" colspan="2"><h2>Excerpt length settings</h2></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Maximum number of sentences to display:</strong><br> Set this setting to a
					value proportional the desired length of the excerpt (about 100 characters per sentence)</td>
				<td width="30%"><input type="text" name="numSentences"
					value="<?php if ( get_option('numSentences') ) echo get_option('numSentences'); else echo 4; ?>" /></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Maximum length in characters:</strong><br> Set the excerpt length limit in
					characters (the last word is not cut in the middle)</td>
				<td width="30%"><input type="text" name="maxLen"
					value="<?php if ( get_option('maxLen') ) echo get_option('maxLen'); else echo 250; ?>" /> characters</td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Articles minimum length to consider</strong><br> Set the length under which
					you don't want to apply any analysis</td>
				<td width="30%"><input type="text" name="minLen"
					value="<?php if ( get_option('minLen') ) echo get_option('minLen'); else echo 50; ?>" /> characters</td>
			</tr>
			<tr>
				<td width="70%" valign="middle" colspan="2"><hr>
					<h2>Look and feel</h2></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Featured image?</strong><br> Display the featured image of your article (if
					available)</td>
				<td width="30%"><input type="checkbox" name="featured" value="1"
					<?php if ( get_option('featured') ) echo 'checked="checked"';  ?> /></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Search image?</strong><br> If no featured image is set display the 
				first image available.</td>
				<td width="30%"><input type="checkbox" name="searchFeatured" value="1"
					<?php if ( get_option('searchFeatured') ) echo 'checked="checked"';  ?> /></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Featured size?</strong><br> Select the size of the picture to display</td>
				<td width="30%">
					<input type="radio" name="featuredSize" value="thumbnail"
					<?php if ( get_option ( 'featuredSize' ) == 'thumbnail'  
							or get_option ( 'featuredSize' ) == '')	echo 'checked="checked"'; ?> />thumbnail 
					<input type="radio" name="featuredSize" value="medium"
					<?php if ( get_option( 'featuredSize' ) == 'medium') echo 'checked="checked"'; ?> />medium 
					<input type="radio" name="featuredSize" value="large"
					<?php if ( get_option( 'featuredSize' ) =='large' ) echo 'checked="checked"'; ?> />large 
					<input type="radio" name="featuredSize" value="full" 
					<?php if ( get_option( 'featuredSize' ) == 'full' ) echo 'checked="checked"'; ?> />full
				</td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Align left or right?</strong><br> The featured picture will get align the
					desired side, with the excerpt written on the side of the image</td>
				<td width="30%"><input type="radio" name="alignFeat" value="alignleft"
					<?php
	
	if (@strpos ( get_option ( 'alignFeat' ), 'left' ) > 0 or get_option ( 'alignFeat' ) == '')
		echo 'checked="checked"';
	?> />left <input type="radio" name="alignFeat" value="alignright"
					<?php if ( @strpos( get_option('alignFeat'),'right' )>0 ) echo 'checked="checked"'; ?> />right</td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Display "Read more"?</strong><br> If you want to add the link/button, simply
					write it in the textbox. Leave the box empty if you don't want to show anything</td>
				<td width="30%"><input type="text" size="50" name="more"
					value="<?php if ( get_option('more') ) echo get_option('more'); ?>" /></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Which html tags do you want to remove?</strong><br> Choose which html tag
					you want to strip of the generated text. It is recommanded to strip the tags having a *</td>
				<td width="30%"><input type="checkbox" name="stripAll" value="1"
					<?php if ( get_option('stripAll') ) echo 'checked="checked"'; ?> />all tags
					<hr> <input type="checkbox" name="stripTags[]" value="img"
					<?php if ( @in_array( 'img', get_option('stripTags') ) ) echo 'checked="checked"'; ?> />*img <input type="checkbox"
					name="stripTags[]" value="br" <?php if ( @in_array( 'br', get_option('stripTags') ) ) echo 'checked="checked"'; ?> />*br
					<input type="checkbox" name="stripTags[]" value="p"
					<?php if ( @in_array( 'p', get_option('stripTags') ) ) echo 'checked="checked"'; ?> />*p <input type="checkbox"
					name="stripTags[]" value="div"
					<?php if ( @in_array( 'div', get_option('stripTags') ) ) echo 'checked="checked"'; ?> />div <input type="checkbox"
					name="stripTags[]" value="a" <?php if ( @in_array( 'a', get_option('stripTags') ) ) echo 'checked="checked"'; ?> />a
					href</td>
			</tr>
			<tr>
				<td width="70%" valign="middle" colspan="2"><hr>
					<h2>Algorithm settings</h2> <br> Uncheck the following two checkboxes for making the plugin function without
					analysing the content</td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Skip the manual excerpt?</strong><br> If you want to avoid displaying the
					excerpt written manually in your post, unflag this checkbox</td>
				<td width="30%"><input type="checkbox" name="skipManual" value="1"
					<?php if ( get_option('skipManual') ) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Use the words in the title (recommanded)?</strong><br> This requests the
					plugin to evaluate the article considering similarities with the title of the article</td>
				<td width="30%"><input type="checkbox" name="useTitle" value="1"
					<?php if ( get_option('useTitle') ) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Use the article tags (if available)?</strong><br> This requests the plugin
					to evaluate the article considering the tags attached to the article</td>
				<td width="30%"><input type="checkbox" name="useTags" value="1"
					<?php if ( get_option('useTags') ) echo 'checked="checked"'; ?> /></td>
			</tr>
			<tr>
				<td width="70%" valign="middle"><strong>Any personal keyword to prioritize?</strong><br> Write in this box any word
					which is particularly relevant to your website and for which you want to increase the priority. <br>Enter the words
					separated by a comma, or leave it blank if you don't to use this feature.</td>
				<td width="30%"><input type="text" size="50" name="custTags"
					value="<?php if ( get_option('custTags') ) echo get_option('custTags'); ?>" /></td>
			</tr>

		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
		</p>
	</form>
	<div id="p3-reminder-wrapper"
		style="border-top-left-radius: 4px; border-top-right-radius: 4px; border-bottom-left-radius: 4px; border-bottom-right-radius: 4px;">
		Do you like this plugin?
		<ul>
			<li><a
				href="http://twitter.com/home?status=I%20just%20enhanced%20my%20WordPress%20excerpts%20with%20%23Smart%20Excerpt%20plugin%20http%3A%2F%2Fwordpress.org%2Fplugins%2Fa-smart-excerpt%2F"
				target="_blank">Tweet about it</a></li>
			<li><a href="https://wordpress.org/plugins/a-smart-excerpt/" target="_blank">Rate it on the repository</a></li>
		</ul>
	</div>
</div>
<?php
}

?>