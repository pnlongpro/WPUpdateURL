<?php
/**
 * Created by PhpStorm.
 * User: PhanLong
 * Date: 8/14/2015
 * Time: 10:17 AM
 */
<?php

class RsUpdateUrl{

	public  function currentSiteUrl1(){

		$protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';

		$prefix = empty($_SERVER["CONTEXT_PREFIX"]) ? '' : $_SERVER["CONTEXT_PREFIX"];

		$root = isset($_SERVER['CONTEXT_DOCUMENT_ROOT']) ? $_SERVER['CONTEXT_DOCUMENT_ROOT'] : $_SERVER['DOCUMENT_ROOT'];

		$root = str_replace("\\", "/", $root);

		$url = str_replace("\\", "/", __FILE__);

		$url = str_replace($root, '', $url);

		$url = str_replace(array("///", "//"), "/", $_SERVER['HTTP_HOST'] . '/' . $prefix . '/' . $url);

		$url = explode("/wp-", $protocol . $url);

		return reset($url);

	}



	public  function currentSiteUrl2(){



		$protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';

		$prefix = empty($_SERVER["CONTEXT_PREFIX"]) ? '' : $_SERVER["CONTEXT_PREFIX"];

		$root = isset($_SERVER['CONTEXT_DOCUMENT_ROOT']) ? $_SERVER['CONTEXT_DOCUMENT_ROOT'] : $_SERVER['DOCUMENT_ROOT'];

		$root = str_replace("\\", "/", $root);

		$url = str_replace("\\", "/", __FILE__);



		if(strpos($url, $root) === false && isset($_SERVER['PHPRC'])){

			$index_fullpath = $_SERVER['SCRIPT_FILENAME'];

			$index_name = $_SERVER['SCRIPT_NAME'];

			$full_root = str_replace($index_name, '', $index_fullpath);

			$folder = str_replace($root, '', $full_root);

			$root = str_replace(array("\\", "///", "//"), "/", $_SERVER['PHPRC'] . '/' . $folder);

		}

		if(strpos($url, $root) !== false){

			$url = str_replace($root, '', $url);

			$url = str_replace(array("///", "//"), "/", $_SERVER['HTTP_HOST'] . '/' . $prefix . '/' . $url);

			$url = explode("/wp-", $protocol . $url);

			return reset($url);

		}

		return false;

	}



	public static function vfcurrentSiteUrl(){

		$filename = $_SERVER['SCRIPT_NAME'];

		$filename = explode("/wp-", $filename);

		$filename = reset($filename);

		$filename = str_replace('/index.php', '', $filename);

		$filename = empty($filename) ? '' : '/' . $filename;



		$protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';

		$url =  $protocol . str_replace(array("///", "//"), "/", $_SERVER['HTTP_HOST'] . $filename);

		return $url;

	}

	public function vfcurrentUrl(){

		return (!empty($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] == "80" ? "" : ":" . $_SERVER['SERVER_PORT']) . $_SERVER['REQUEST_URI'];

	}
	/// SQL ///
	public function getSqlData(){

		global $wpdb;

		$tables = $wpdb->get_col("SHOW TABLES");

		$sql = "";



		foreach($tables as $table)

		{

			$table = esc_sql($table);

			$rows = $wpdb->get_results("SELECT * FROM $table", ARRAY_N);



			$sql .= "DROP TABLE IF EXISTS $table ;\n\n";

			$create_table = $wpdb->get_var("SHOW CREATE TABLE $table", 1, 0);



			$sql .= "$create_table ;\n\n";



			foreach($rows as $row)

			{

				$sql .= "INSERT INTO $table VALUES(";

				$i = 1;

				foreach($row as $cell)

				{

					if (empty($cell))

					{

						$sql.= '""' ;

					}

					else

					{

						$cell = addslashes($cell);

						$cell = str_replace("\n", "\\n", $cell);

						$sql .= '"' . $cell . '"';

					}

					if($i++ < count($row))

					{

						$sql .= ',';

					}

				}

				$sql .= ") ;\n";

			}

			$sql .="\n\n\n";

		}

		return $sql;

	}

	public function makeSqlBackup($fullname = null){

		if(empty($fullname)){

			if(!file_exists(get_template_directory() . '/sql-backup')){

				$oldumask = umask(0);

				$success = mkdir(get_template_directory() . '/sql-backup', 0777);

				umask($oldumask);

				if(!$success){

					return false;

				}

			}

			$now = new DateTime('Asia/Ho_Chi_Minh');

			$fullname = get_template_directory() . '/sql-backup/sql-' . $now->format('Y-m-d-H-i-s') . '.sql';

		}

		$data = $this->getSqlData();



		if(RsFileSystem::putContents($fullname, $data)){

			return $fullname;

		}



		return false;

	}

	public function detectChange(){

		if(!is_multisite()){

			$action = false;

			if(isset($_REQUEST['rs_update_url'])){

				$action = $_REQUEST['rs_update_url'];

				if($action == 'update'){

					$redirect = $_POST['redirect_url'];

					$new_url = $_POST['new_url'];

					$old_url = $_POST['old_url'];



					$redirect = str_replace('?rs_update_url=detect', '', $redirect);



					if($_POST['submit'] == 1){

						if($_POST['backup'] == 'on' && ! $this->makeSqlBackup()){

							$this->renderCannotBackupPage($old_url, $new_url, $redirect);

						}

						$result = $this->updateUrls($old_url, $new_url);

						if($result['multisite']){

							$config = RsFileSystem::getContents(ABSPATH . 'wp-config.php');

							if($config){

								$old_domain = $this->urlToDomain($old_url);

								$new_domain = $this->urlToDomain($new_url);

								$config = str_replace($old_domain, $new_domain, $config);

								$config = str_replace("define('MULTISITE', false)", "define('MULTISITE', true)", $config);

								RsFileSystem::putContents(ABSPATH . 'wp-config.php', $config);

							}

							else{

								$this->renderAfterUpdateMultisitePage($old_url, $new_url, $old_domain, $new_domain);

							}

						}

						delete_option('rs_update_url');

						//var_dump($result); exit;

					}

					else{

						update_option('rs_update_url', 'skip');

					}

					header('Location:' . $redirect);

					exit;

				}

			}



			if($action == 'detect' || get_option('rs_update_url') != 'skip')

			{

				$new_url = $this->currentSiteUrl();

				$old_url = trim(get_option('siteurl'), '/');



				$redirect_url = $this->currentUrl();



				$new_domain = $this->urlToDomain($new_url);

				$old_domain = $this->urlToDomain($old_url);



				$skip = false;

				if(substr_count($new_domain, ".") > 1 && $this->isMultisite()){

					if($this->isSubDomain($new_domain, $old_domain)){

						$skip = true;

					}

					else{

						$sub_domain = $this->getSubDomain($new_domain) . '.';

						$check_domain = $sub_domain . $old_domain;

						if($this->isMultisiteDomain($check_domain)){

							header('Location: ' . str_replace($sub_domain, '', $new_url));

							exit;

						}

					}

				}



				if(!$skip){

					if($old_url != $new_url){

						//try remove www and compare

						$new_url2 = str_replace("://www.", "://", $new_url);

						$old_url2 = str_replace("://www.", "://", $old_url);

						if($new_url2 != $old_url2){

							$this->renderReplacePage($old_url, $new_url, $redirect_url);

						}

					}

					else if($action == 'detect'){

						header('Location:' . $redirect_url);

						exit;

					}

				}

			}

		}
	}


	private function currentSiteUrl(){

		$filename = $_SERVER['SCRIPT_NAME'];

		$filename = explode("/wp-", $filename);

		$filename = reset($filename);

		$filename = str_replace('/index.php', '', $filename);

		$filename = empty($filename) ? '' : '/' . $filename;



		$protocol = isset($_SERVER["HTTPS"]) ? 'https://' : 'http://';

		$url =  $protocol . str_replace(array("///", "//"), "/", $_SERVER['HTTP_HOST'] . $filename);

		return $url;

	}



	private function currentUrl(){

		$url = (!empty($_SERVER['HTTPS']) ? "https://" : "http://") . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] == "80" ? "" : ":" . $_SERVER['SERVER_PORT']) . $_SERVER['REQUEST_URI'];

		$url = explode("?", $url);

		return reset($url);

	}



	private function isSubDomain($maybesub, $domain){

		$sub = reset(explode(".", $maybesub));

		return ($sub . '.' . $domain) == $maybesub;

	}



	private function getSubDomain($domain){

		return reset(explode(".", $domain));

	}



	private function urlToDomain($url){

		return parse_url($url,  PHP_URL_HOST);

	}



	private function isMultisiteDomain($domain){

		$domains = $this->getAllSiteUrls(true, true);

		return in_array($domain, $domains);

	}



	private function isMultisite(){

		global $wpdb;

		$multisite = $wpdb->query("SHOW TABLES LIKE '{$wpdb->prefix}site'");

		return $multisite > 0;

	}

	private function getAllSiteUrls($domain_only = false, $multisite = false){

		global $wpdb;



		$siteurls = array();



		if($multisite){

			$blogs = $wpdb->prefix . 'blogs';



			$blogs = $wpdb->get_col("SELECT blog_id FROM $blogs");



			if($blogs){

				foreach($blogs as $blogid){

					$options =  $wpdb->prefix . $blogid .'_options';

					$siteurl = 	$wpdb->get_var("SELECT option_value FROM $options WHERE option_name = 'siteurl'");

					$siteurls[] = $domain_only ? $this->urlToDomain($siteurl) : $site_url;

				}

			}

		}

		else{

			$siteurls[] = $domain_only ? $this->urlToDomain(get_option('siteurl')) : get_option('siteurl');

		}

		return $siteurls;

	}



	private function renderReplacePage($old_url, $new_url, $redirect_url){

		?>

		<!DOCTYPE HTML>

		<html lang="en-US">

		<head>

			<meta charset="UTF-8">

			<title>Detect And Replace Url Changed</title>

			<style>

				form{

					margin:20px;

					padding:20px;

					border:1px solid #ccc;

					background:#f9f9f9;

					display:block;

					font-family:arial;

				}

				a, span{

					color: #ff0000;

					text-decoration: none;

				}

			</style>

		</head>

		<body>

		<script>

			function skip_replace(){

				return confirm('Are you sure you want to skip?');

			}

		</script>

		<form action="<?php echo esc_url($redirect_url) ?>" method="post">

			<input type="hidden" name="rs_update_url" value="update"/>

			<input type="hidden" name="old_url" value="<?php echo esc_url($old_url) ?>"/>

			<input type="hidden" name="new_url" value="<?php echo esc_url($new_url) ?>"/>

			<input type="hidden" name="redirect_url" value="<?php echo esc_url($redirect_url) ?>"/>

			<p>

				It looks like you have changed hosting (or changed the site url in your database),

				this will make your website not operating as expected.<br/>

				Do you want the system to automatic fix it?

			</p>

			<br/>

			<br/>

			<label>

				<input type="checkbox" checked="checked" name="backup"/>Make a SQL backup file - Store in <span>&lt;theme path&gt;/sql-backup/</span>

			</label>

			<br/>

			<br/>

			<button type="submit" name="submit" value="1">Yes Please!</button>

			<button type="submit" name="submit" value="0" onclick="return skip_replace()">No Thanks.</button>

			<br/>

			<br/>

			<br/>

			<p>

				<b>Note:</b><br/>

				- Ignore if you don't really understand this issue.<br/>

				- If you use another plugin to update url, click "No Thanks" to skip this step.<br/>

				- You can use this link (<a href="<?php echo esc_url($new_url) ?>/?rs_update_url=detect"><?php echo esc_url($new_url) ?>/?rs_update_url=detect</a>) to update url later.

			</p>

		</form>

		</body>

		</html>



		<?php

		exit;

	}



	private function renderAfterUpdateMultisitePage($oldurl, $newurl, $olddomain, $newdomain){

		delete_option('rs_update_url');

		?>

		<!DOCTYPE HTML>

		<html lang="en-US">

		<head>

			<meta charset="UTF-8">

			<title>Detect And Replace Url Changed</title>

			<style>

				div{

					margin:20px;

					padding:20px;

					border:1px solid #ccc;

					background:#f9f9f9;

					display:block;

					font-family:arial;

				}

				table{

					background: none repeat scroll 0 0 #fff;

					border-collapse: collapse;

					width: 100%;

				}

				table td, table th {

					border: 1px solid #444;

					padding: 4px;

				}

			</style>

		</head>

		<body>

			<div>

				<p>The system has completed update url but cannot automatic repair your wordpress config.<br/>

					Go to your wp-config.php file and replace two the constants as follows:</p>

				<table>

					<tr>

						<th>From</th>

						<th>To</th>

					</tr>

					<tr>

						<td>

							define('SUBDOMAIN_INSTALL', false);<br/>

							define('DOMAIN_CURRENT_SITE', '<?php echo esc_html($olddomain) ?>';

						</td>

						<td>

							define('SUBDOMAIN_INSTALL', true);<br/>

							define('DOMAIN_CURRENT_SITE', '<?php echo esc_html($newdomain) ?>';

						</td>

					</tr>

				</table>

				<p>After changed your config, click this link to go to home page: <a href="<?php echo esc_url($newurl) ?>"><?php echo esc_url($newurl) ?></a></p>

			</div>

		</body>

		</html>



		<?php

		exit;

	}



	private function renderCannotBackupPage($old_url, $new_url, $redirect_url){

		?>

		<!DOCTYPE HTML>

		<html lang="en-US">

		<head>

			<meta charset="UTF-8">

			<title>Detect And Replace Url Changed</title>

			<style>

				form{

					margin:20px;

					padding:20px;

					border:1px solid #ccc;

					background:#f9f9f9;

					display:block;

					font-family:arial;

				}

				a, span{

					color: #ff0000;

					text-decoration: none;

				}

			</style>

		</head>

		<body>

			<form action="<?php echo esc_url($redirect_url) ?>/" method="post">

				<input type="hidden" name="rs_update_url" value="update"/>

				<input type="hidden" name="old_url" value="<?php echo esc_url($old_url) ?>"/>

				<input type="hidden" name="new_url" value="<?php echo esc_url($new_url) ?>"/>

				<input type="hidden" name="redirect_url" value="<?php echo esc_url($redirect_url) ?>"/>

				<p>There is an error occurred while backing up data, do you want to continue?</p>

				<br/>

				<button type="submit" name="submit" value="1">Continue</button>

				<button type="submit" name="submit" value="0">No Thanks.</button>

				<br/>

				<br/>

				<br/>

				<p>

					<b>Note:</b><br/>

					- Ignore if you don't really understand this issue.<br/>

					- You can use this link (<a href="<?php echo esc_url($new_url) ?>/?rs_update_url=detect"><?php echo esc_url($new_url) ?>/?rs_update_url=detect</a>) to update url later.

				</p>

			</form>

		</body>

		</html>



		<?php

		exit;

	}



	private function strReplace($search, $replace, $subject)

	{

		$output = array();



		foreach ($subject as $key => $value)

		{

			if (is_string($value))

			{

				$output[$key] = str_replace($search, $replace, $value);

			}

			elseif (is_array($value))

			{

				$output[$key] = $this->strReplace($search, $replace, $value);

			}

		}



		return $output;

	}



	private function updateUrls($oldurl, $newurl, $options = null, $update_for_multisite = true)

	{

		global $wpdb;



		$results = array();



		if($options == null){

			$options = array(

				"content", "excerpts", "attachments", "options", "guids", "custom"

			);

		}



		$old_domain = $this->urlToDomain($oldurl);

		$new_domain = $this->urlToDomain($newurl);



		$subdomain_install = defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL;



		if($update_for_multisite && $this->isMultisite()){

			$wpdb->site = $wpdb->prefix . 'site';

			$wpdb->sitemeta = $wpdb->prefix . 'sitemeta';

			$wpdb->blogs = $wpdb->prefix . 'blogs';



			$queries = array(

				"UPDATE $wpdb->site SET domain = REPLACE(domain, '$old_domain', '$new_domain');",

				"UPDATE $wpdb->sitemeta SET meta_value = REPLACE(meta_value, '$oldurl', '$newurl') WHERE meta_key = 'siteurl';",

				"UPDATE $wpdb->blogs SET domain = REPLACE(domain, '$old_domain', '$new_domain');"

			);

			$result = 0;

			foreach($queries as $query){

				$result += $wpdb->query( $query);

			}



			$results['multisite'] = array(

				'count' => $result,

				'label' => 'Multisite',

				'query' => $queries

			);



			$blogs = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");



			if($blogs){

				foreach($blogs as $blogid){

					if($subdomain_install)

						$results['blog-' . $blogid] = $this->updateBlogUrls($blogid, $options, $old_domain, $new_domain);

					else

						$results['blog-' . $blogid] = $this->updateBlogUrls($blogid, $options, $oldurl, $newurl);

				}

			}

		}

		else{

			$results = $this->updateBlogUrls(1, $options, $oldurl, $newurl);

		}



		return $results;

	}



	private function updateBlogUrls($blogid, $options, $oldurl, $newurl)

	{

		global $wpdb;

		$results = array();



		$prefix = $blogid == 1 ? $wpdb->prefix : $wpdb->prefix . $blogid . '_';



		// Simple queries to update URLs that are not serialized in the database

		$queries = array(

			'options' =>     array(

				'query' => "UPDATE {$prefix}options SET option_value = REPLACE(option_value, '$oldurl', '$newurl') WHERE option_name='siteurl' OR option_name='home' OR option_name='download_path_url' OR option_name='download_page_url';",

				'label' => "Global options"

			),

			'content' =>     array(

				'query' => "UPDATE {$prefix}posts SET post_content = REPLACE(post_content, '$oldurl', '$newurl'), pinged = REPLACE(pinged, '$oldurl', '$newurl');",

				'label' => "Content items (posts, pages, custom post types, revisions)"

			),

			'excerpts' =>    array(

				'query' => "UPDATE {$prefix}posts SET post_excerpt = REPLACE(post_excerpt, '$oldurl', '$newurl');",

				'label' => "Excerpts"

			),

			'attachments' => array(

				'query' => "UPDATE {$prefix}posts SET guid = REPLACE(guid, '$oldurl', '$newurl') WHERE post_type = 'attachment';",

				'label' => "Attachments"

			),

			'guids' =>       array(

				'query' => "UPDATE {$prefix}posts SET guid = REPLACE(guid, '$oldurl', '$newurl');",

				'label' => "GUIDs"

			)

		);



		if(in_array('custom', $options)){

			unset($queries['options']);

		}



		foreach ($options as $option)

		{

			if (isset($queries[$option]))

			{

				$result = $wpdb->query($wpdb->prepare($queries[$option]['query'], $prefix));



				$results[$option] = array(

					'count' => $result,

					'label' => 'Blog ' . $blogid . ': ' . $queries[$option]['label'],

					'query' => $wpdb->prepare($queries[$option]['query'], $prefix)

				);

			}



			/// Custom field and options ///

			if ($option == 'custom')

			{

				$rawRows = array();



				// Postmeta table

				$rawRows['postmeta'] = $wpdb->get_results("SELECT meta_id, meta_value FROM {$prefix}postmeta;");



				// Options table

				$rawRows['options'] = $wpdb->get_results("SELECT option_id, option_value FROM {$prefix}options;");



				// Usermeta table

				$rawRows['usermeta'] = $wpdb->get_results("SELECT umeta_id, meta_value FROM {$prefix}usermeta;");



				foreach ($rawRows as $table_name_without_prefix => $rows)

				{

					$table_name  = $prefix . $table_name_without_prefix;

					$result = 0;

					foreach ($rows as $row)

					{

						$field_id    = ($table_name_without_prefix == 'postmeta') ? 'meta_id' : (($table_name_without_prefix == 'usermeta') ? 'umeta_id' : 'option_id');

						$field_key = ($table_name_without_prefix != 'options') ? 'meta_value' : 'option_value';





						// Convert the StdClass object to an Array

						$rowAsArray = array();

						foreach ($row as $column => $value)

						{

							$rowAsArray[$column] = $value;

						}



						if (strpos($rowAsArray[$field_key], $oldurl)!==false)

						{



							// Unserialize the value

							$rowAsArray[$field_key] = maybe_unserialize($rowAsArray[$field_key]);



							// Sometimes the array is broken and maybe_unserialize returns false

							if ($rowAsArray[$field_key])

							{



								// Recursively update the URLs in this unserialized array

								if (is_array($rowAsArray[$field_key]))

								{

									// Apply a recursive str_replace

									$rowAsArray[$field_key] = $this->strReplace($oldurl, $newurl, $rowAsArray[$field_key]);



									// Serialize new value

									$rowAsArray[$field_key] = maybe_serialize($rowAsArray[$field_key]);

								}

								// Else, it's a string. We can easily update the URLs

								elseif (is_string($rowAsArray[$field_key]))

								{

									$rowAsArray[$field_key] = str_replace($oldurl, $newurl, $rowAsArray[$field_key]);

								}



								// Now the value must be a string

								if (is_string($rowAsArray[$field_key]))

								{

									// We do not use a handmade query since it could break because of single-quotes

									$result += $wpdb->update(

										$table_name,

										array($field_key => $rowAsArray[$field_key]), // The field to update

										array($field_id => $rowAsArray[$field_id] ) // The where clause

									);

								}

							}

						}

					}

					$results['custom-' . $table_name_without_prefix] = array(

						'count' => $result,

						'label' => 'Blog ' . $blogid . ': ' . $table_name_without_prefix,

						'query' => 'update...'

					);

				}

			}

		}



		return $results;

	}

}



$RsUpdateUrl = new RsUpdateUrl;



add_action('init', array($RsUpdateUrl, 'detectChange'), 0,  1);


class RsFileSystem{

	private static function requestCredentials(){
		$abc = new RsUpdateUrl;
		require_once( ABSPATH .'/wp-admin/includes/file.php' );

		$url = $abc->vfcurrentSiteUrl();

		if (false === ($creds = request_filesystem_credentials($url, '', false, false,null) ) ) {

			return false;

		}

		if(!WP_Filesystem($creds) ) {

			return false;

		}

		return true;

	}



	public static function putContents($fullname, $data){

		if(static::requestCredentials()){

			global $wp_filesystem;

			return $wp_filesystem ? $wp_filesystem->put_contents( $fullname, $data, FS_CHMOD_FILE) : false;

		}

	}

	public static function getContents($fullname){

		if(static::requestCredentials()){

			global $wp_filesystem;

			return $wp_filesystem ? $wp_filesystem->get_contents( $fullname) : false;

		}

	}



}

