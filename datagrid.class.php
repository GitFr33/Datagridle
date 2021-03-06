<?php
/*
    PhotoSynthesis Datagridle
    http://www.photosynth.ca/code/datagridle

    Ultra-rapid, customizable database content editing interface.

    This class is released under the GPL license. If you would like to use it
    in proprietary applications, versions under more permissive licenses can
    be provided. Write to adam@photosynth.ca.

    Suggestions and feedback are welcome.

    Copyright (C) 2013  A. McKenty

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

*/


class datagrid{
  var $paths;
  var $settings;
  var $modes = array();
  var $mode;
  var $struct = array();
  var $fields;
  var $tabletitle;
  var $url;
  var $redirect_url;

  var $head_links = array(
    'css' => array('datagrid.css'),
    'js' => array('calendarDateInput.js','jquery.js','datagrid.js'));

  var $user_head_links = array(
    'css' => array(),
    'js' => array()
  );

  var $dg_name;
  var $dg_display_name;
  var $ses_name;
  var $searchtype;
  var $searchfeedback;
  var $searchquery;
  var $searchsql;
  var $sortfield;
  var $sorttable;
  var $sorttype;
  var $newsorttype;
  var $feedback;
  var $feedback_class;
  var $queriedrowsql;
  var $totalrowsql;
  var $totalrows;
  var $queriedrows;
  var $displayedrows;
  var $start;
  var $limit;
  var $sql;
  var $db;
  var $rowcount;
  var $primary_table;
  var $debug_level = 2;
  var $reports = array();

  function __construct($primary_table,$db = NULL,$settings = NULL){


    // if $db is database object, use that;
    // else if db is string, use as database name and get credentials from config
    // else if db is array, get credentials from array
    // else if db is a mysql link resource, pass to the db class
    // else use everything from config

    if(is_callable('session_status')){
      if (session_status() == PHP_SESSION_NONE) {
        session_start();
      }
    }else if (session_id() == '' && headers_sent() == false){
      session_start();
    }

    $ds = DIRECTORY_SEPARATOR;

    $working_abs_path = getcwd().$ds;

    $dg_abs_path = dirname(__FILE__).$ds;

    $doc_root = $_SERVER['DOCUMENT_ROOT'];

    $this->dg_relative_path = '/'.str_replace($doc_root,'',str_replace('\\','/',$dg_abs_path));


    if(file_exists($working_abs_path.'dg_config.php')){
      include($working_abs_path.'dg_config.php');
    }else{
      include($dg_abs_path.'dg_config.php');
    }

    $this->secure_salt = $secure_salt;


    if(file_exists($working_abs_path.'dg_paths.php')){
      include($working_abs_path.'dg_paths.php');
    }else{
      include($dg_abs_path.'dg_paths.php');
    }

    if(file_exists($working_abs_path.'dg_modes.php')){
      include($working_abs_path.'dg_modes.php');
    }else{
      include($dg_abs_path.'dg_modes.php');
    }

    if(!class_exists('database')){
      if(file_exists($this->paths['classes_path'].'database.class.php')){
        include($this->paths['classes_path'].'database.class.php');
      }else if(file_exists($dg_abs_path.'dependencies/database.class.php')){
        include($dg_abs_path.'dependencies/database.class.php');
      }else{
        $this->exit_error('Database interaction class file not found');
      }
    }

    if(!class_exists('url')){
      if(file_exists($this->paths['classes_path'].'url.class.php')){
        include($this->paths['classes_path'].'url.class.php');
      }else if(file_exists($dg_abs_path.'dependencies/url.class.php')){
        include($dg_abs_path.'dependencies/url.class.php');
      }else{
        $this->exit_error('URL management class file not found');
      }
    }

    if(is_object($db)){
      $this->db = $db;
    }elseif(is_resource($credentials)){
       if(get_resource_type($credentials) == 'mysqli'){
         $this->db = new database($credentials);

       }
    }else{

      if(is_array($db)){
        foreach ($db as $key=>$value) {
          $db_config[$key] = $value;
        }
      }elseif(is_string($db)){
        $db_config['db'] = $db;
      }

      $this->db = new database($db_config);
    }

    if(count($this->db->errors) > 0){
      $this->exit_error('Count not create database class instance. Database connection failed. <b>Please check that the supplied database connection info is correct.</b><br />Database error:'.$this->db->text_errors());
    }

    if($settings['dg_name']){
      $this->dg_name = $settings['dg_name'];
    }else{
      $this->dg_name = $primary_table;
    }

    if(!$settings['unique_dg_id']){
      //$trace = debug_backtrace();
      //$calling_file = $trace[0]['file'];
      $this->unique_dg_id = "dg".substr(md5($primary_table),0,5);
    }else{
      $this->unique_dg_id = $settings['unique_dg_id'];
    }

    $this->dbg('ugid in __construct',$this->unique_dg_id);

    $this->ses_name = $this->unique_dg_id;

    /* FUTURE CACHING SCHEME
    if(file_exists('cache/'.$dg_name.'_structure_cache.txt'){
      //read in xml, php, or csv file and parse into php struct array
      //best, simplest: write a php file with the array defined in it, then just include it
    }
    */


    if(!$structure){
      $this->primary_table = $primary_table;
      $this->auto_detect_fields($primary_table);
    }else{
      $this->struct = $structure;
    }

    if(!$this->session('settings')){

      $this->settings = Array(
        'privileges' => 'edit,add,delete',
        'display_name' => str_replace('_',' ',ucfirst($this->dg_name)),
        'defaultsort' => '',
        'defaultsearch' => "",
        'defaultlimit' => 50,
        'content_css' => 'css/pagecontent.css',
        'mode' => 'full',
        'tmce_version' => '',
        'global_debug_function' => 'testit',
        'unique_GET_prefix' => $this->unique_dg_id."_",
        'search_child_tables' => true
      );

      if($settings){
        foreach ($settings as $key=>$value) {
          $this->settings[$key] = $value;
        }
      }

      $this->set_session('settings',$this->settings);

    }else{
      $this->settings = $this->session('settings');
    }

    if($this->GET('mode')){
      $this->set_setting('mode',$this->GET('mode'));
    }


    $this->target_url = new url();
  }

  function setup_child_grid(){

    $ugp = $this->settings['unique_GET_prefix'];

    $this->ses_name .= '_'.$this->GET('link_value');
    $this->target_url->set_query_pair($ugp.'mode','child');

    $parent_link_field = $this->GET('parent_link_field');
    $child_link_field = $this->GET('child_link_field');
    $link_value = $this->GET('link_value');

    $this->target_url->set_query_pair($ugp.'parent_link_field',$parent_link_field);
    $this->target_url->set_query_pair($ugp.'child_link_field',$child_link_field);
    $this->target_url->set_query_pair($ugp.'link_value',$link_value);

    $this->detailssql = $this->primary_table.'.'.$child_link_field." = '$link_value'";

    $this->set_field_attribs($this->primary_table,$child_link_field,array(
          'type' => 'readonly,hidden',
          'default' => $link_value
          ));

    $this->set_setting('display_name',$this->setting('display_name').' for '.$child_link_field.' '.$link_value);
    $this->set_setting('default_hidden_fields',array($this->primary_table.$child_link_field));
  }

  function get_search_fields(){

    if($this->setting('search_fields')){
      $search_fields = $this->setting('search_fields');
    }else{
      $search_fields = array();
      foreach ($this->struct as $t=>$t_ats) {
         $this->dbg("Struct[$t]",$t_ats);
         if($t_ats['type'] != 'child' || $this->setting('search_child_tables') == true){
           foreach ($t_ats['fields'] as $f=>$f_ats) {
             if($f_ats['type'] != 'derivative'){
           	   $search_fields[$t.'.'.$f] = $f_ats;
             }
           }
         }
      }
    }
    $this->dbg('Search fields',$search_fields);
    return $search_fields;
  }

  function search_tab(){
    $search_block .= '
    <form action="'.$this->target_url->get().'" method="GET"  style="float:left" class="search_form">
    ';
    $search_block .= $this->target_url->get_hidden_inputs();
    $search_block .= '
    <select name="'.$this->GET_pfx().'searchfield" class="search">';


    $search_fields = $this->get_search_fields();


    foreach ($search_fields as $field => $fieldattribs){
        if($field == $this->searchfield || (!$this->searchfield && $fieldattribs['searchdefault'])){
          $selected = 'selected';
        }else{
          $selected = '';
        }
        $search_block .= "<option value=\"$field\" $selected>$fieldattribs[title]</option>";
    }
    $search_block .= '
    </select>
    <select name="'.$this->GET_pfx().'searchtype" class="search">';
    $type_options = array('includes','equals','less_than','greater_than');
    foreach ($type_options as $key=>$value){
        if($value == $this->searchtype){
          $selected = 'selected';
        }else{
          $selected = '';
        }
        $search_block .= "<option $selected>$value</option>";
    }
    $search_block .= '
    </select>
    <input type="text" class="search" name="'.$this->GET_pfx().'searchquery" value="'.$this->searchquery.'"/>
    <button type="submit" style="float: none;" class="search">Search</button></form>';
    if($this->searchtype && $this->searchfield && $this->searchquery){
      $search_block .= '
      <form action="'.$this->target_url->get().'" method="GET" style="float:left" class="search_form">
      ';
      $search_block .= $this->target_url->get_hidden_inputs();
      $search_block .= '
      <input type="hidden" name="'.$this->GET_pfx().'searchquery" value="showall">
      <button type="submit" class="search" style="margin-left: 10px;">Reset</button>';
      $search_block .= '
      </form>';
    }
    $search_block .= '<br clear="all"/>';

    $search_block .= '
    </form>';
    return $search_block;
  }





  function paging_tab(){

    //Paging input
    $paging_block .= "
    <form action=\"".$this->target_url->get()."\" method=\"GET\"  class=\"paging_form\">
    ";
    $paging_block .= $this->target_url->get_hidden_inputs();
    $paging_block .= "
    Showing up to
    <input type=\"text\" class=\"paging\" style=\"float: none;\" name=\"".$this->GET_pfx()."limit\" value=\"".$this->limit."\" size=\"4\">
    items per page, starting with item
    <input type=\"text\" name=\"".$this->GET_pfx()."start\" class=\"paging\" style=\"float: none;\" size=\"4\" value=\"".$this->start."\">
    <button type=\"submit\" style=\"float: none\" class=\"paging\" value=\"Update\">Update</button>
    </form>";
    return $paging_block;

  }

  function grid_controls(){
    $out = '
<script language="javascript" type="text/javascript" src="'.$this->paths['js_path'].'jquery.idTabs.min.js"></script>
';

    $control_tabs = array(
      'search' => "Search",
      'paging' => "Paging",
    );

    if(count($this->reports) > 0){
      $control_tabs['reports'] = "Reports";
    }

    foreach ($control_tabs as $key=>$value) {
      if($this->modes[$this->setting('mode')]['show_'.$key]){
        $function = $key."_tab";
        $tab_headers .= '<li><a href="#'.$key.'_tab">'.$value.'</a></li>';
        $content = $this->$function();
        $tabs .= '<div id="'.$key.'_tab">'.$content.'</div>';

      }

    }

    $out .= '
    <div id="control_tabs" class="control_tabs">
    <ul>
    '.$tab_headers.'
    </ul>
    '.$tabs.'
    </div>
    <script type="text/javascript">
    $("#control_tabs ul").idTabs();
    </script>';

    return $out;
  }

  function search(){

    $this->dbg('Search vars',$this->searchfield.$this->searchtype.$this->searchquery);

    if ($this->GET('searchquery') == 'showall'){

       $this->unset_session('searchfield');
       $this->unset_session('searchquery');
       $this->unset_session('searchtype');

       unset($this->searchsql,$this->searchquery,$this->searchfield,$this->searchfeedback);

    }elseif ($this->GET('searchquery') && $this->GET('searchtype') && $this->GET('searchfield')){

      $this->searchfield = $this->GET('searchfield');
      $this->searchtype = $this->GET('searchtype');
      $this->searchquery = $this->GET('searchquery');

      $this->set_session('searchfield',$this->searchfield);
      $this->set_session('searchtype',$this->searchtype);
      $this->set_session('searchquery',$this->searchquery);

      // Reset paging

      $this->start = 0;
      $this->set_session('start',0);

    }elseif($this->session('searchquery')){

      $this->searchfield = $this->session('searchfield');
      $this->searchtype = $this->session('searchtype');
      $this->searchquery = $this->session('searchquery');

    }elseif(is_array($this->setting('defaultsearch'))){

      $defsearch = $this->setting('defaultsearch');

      if($defsearch['field'] && $defsearch['type'] && $defsearch['value']){

        $this->searchfield = $defsearch['field'];
        $this->searchtype = $defsearch['type'];
        $this->searchquery = $defsearch['value'];

      }

    }


    if($this->searchquery && $this->searchfield && $this->searchtype){

      $this->searchfeedback = "Showing items where <b>".$this->searchfield."</b> ".$this->searchtype." <b>\"".$this->searchquery."\"</b>";

      $field_parts = explode('.',$this->searchfield);

      $this->dbg('Field parts',$field_parts);

      if($this->struct[$field_parts[0]]['fields'][$field_parts[1]]){ // Make sure field is legit

        $this->searchsql = $this->searchfield;

        if ($this->searchtype == "includes"){
          $this->searchsql .= " LIKE ";
          $this->searchsql .= "'%".$this->escape_str($this->searchquery)."%'";
        }elseif($this->searchtype == "greater_than"){
          $this->searchsql .= " > ";
          $this->searchsql .= "'".$this->escape_str($this->searchquery)."'";
        }elseif($this->searchtype == "less_than"){
          $this->searchsql .= " < ";
          $this->searchsql .= "'".$this->escape_str($this->searchquery)."'";
        }else{
          $this->searchsql .= " = ";
          $this->searchsql .= "'".$this->escape_str($this->searchquery)."'";
        }
      }else{
        $this->set_feedback('Searched field ('.$this->searchfield.') doesn\'t exist!','warning');
      }
    }
  }




// Process sort order

// If not in the get, check session
// if not in session, check the default variable
// if default not set, use first field

function sort(){
  if($this->GET('sortfield')){
    $attribs = $this->field_attribs($this->GET('sorttable'),$this->GET('sortfield'));
    if(!$attribs['noquery']){
      $this->sorttable = $this->GET('sorttable');
      $this->sortfield = $this->GET('sortfield');
      $this->sorttype = $this->GET('sorttype');
    }
  }
  elseif($this->session('sortfield')){
    $this->sortfield = $this->session('sortfield');
    $this->sorttable = $this->session('sorttable');
    $this->sorttype = $this->session('sorttype');
  }
  elseif (is_array($this->settings['defaultsort'])) {
    $this->sortfield = $this->settings['defaultsort']['field'];
    $this->sorttable = $this->settings['defaultsort']['table'];
    $this->sorttype = $this->settings['defaultsort']['type'];
  }
  else{
    $this->sorttable = $this->primary_table;
    $this->sortfield = $this->primary_field;
    $this->sorttype = "DESC";
  }

  // Check for missing bits
  if(!$this->sorttype){
     $this->sorttype = "DESC";
  }
  if(!$this->sorttable){
     $this->sorttable = $this->primary_table;
  }


  $this->set_session('sortfield',$this->sortfield);
  $this->set_session('sorttable',$this->sorttable);
  $this->set_session('sorttype',$this->sorttype);

  $this->sorttitle = $this->field_attrib($this->sorttable.$this->sortfield,'title');
}

  function rowcount_feedback(){
    if($this->mode_shows('result_counts')){
      $startplus = ($this->start+ 1);
      $finish = ($this->start + $this->limit);
      if($finish > $this->queriedrows){
        $finish = $this->queriedrows;
      }

      if ($this->searchquery){
          return "Showing records $startplus - $finish out of ".$this->queriedrows." records that matched your query";
      }else{
          return "Showing records $startplus - $finish out of ".$this->totalrows." records in the table. ";
      }
    }
  }

  function paging(){

    // Process the start and limit variables
    // If not in the get, check session
    // if not in session, use defaults
    if ($this->GET('limit') === NULL){
      if ($this->session('limit') === NULL){
        $this->limit = $this->settings['defaultlimit'];
      }else{
        $this->limit = $this->session('limit');
      }
    }else{
      $this->limit = $this->GET('limit');
      $this->set_session('limit',$this->limit);
    }

    if ($this->GET('start') === NULL){
      if ($this->session('start') === NULL){
        $this->start = '0';
      }else{
        $this->start = $this->session('start');
      }
    }else{
      $this->start = $this->GET('start');
      if (!$this->start){
          $this->start = '0';
      }
      $this->set_session('start',$this->start);
    }

  }


  function set_setting($var,$value){
    $this->settings[$var] = $value;
  }

  function set_settings($array){
    foreach ($array as $key=>$value) {
      $this->settings[$key] = $value;
    }
  }

  function setting($setting){
   return $this->settings[$setting];
  }




  function item_delete(){
    if($this->GET('id')){
      if($this->setting('delete_callback')){
          $dele_cb_func = $this->setting('delete_callback');
          if(function_exists($dele_cb_func)){
             $dele_cb_func($this->GET('id'));
          }else{
            $this->exit_error('Undefined or inaccessible delete callback: '.$dele_cb_func);
          }
      }

      $id = $this->db->escape_str($this->GET('id'));

      $this->db->delete($this->get_primary_table(),$this->get_primary_key()." = '".$id."'");

      return "Item ".$this->GET('id')." deleted";
    }else{
      $this->dbg("Error in item_delete(): missing key");
    }
  }

  function process(){
    $action = $this->POST('action');
    $update_data = $_POST['data'];
    $key_field = $_POST['key_field'];
    $key_value = $_POST['key_value'];
    $table = $this->get_primary_table();

    //if($_POST['continue_edit']){
    //  $this->continue_edit = array('table' => $table,'key_field' => $key_field,'key_value' => $key_value);
    //  $this->set_session('continue_edit',$this->continue_edit);
    //}

    if($_POST['continue'] == 'on'){
      $this->set_GET('action','edit');
      $this->set_GET('id',$key_value);
    }

    $this->redir_page = $_POST['redir_page'];

    if($_POST['non_array_input_names']){
      foreach ($_POST['non_array_input_names'] as $key=>$value) {
        $db_field = substr($value, 10);
      	$update_data[$db_field] = $_POST[$value];
      }
    }

    foreach ($_POST['data'] as $field => $value){

      // Check for callbacks
      $attribs = $this->field_attribs($table,$field);
      if($callback = $attribs['save_callback']){

        if(function_exists($callback)){
          $update_data[$field] = $callback($table,$field,$value,$_POST['data'],$key_value);

        }else{
          $this->dbg('Save callback error - undefined function',$callback);
        }
      }

      if($attribs['noinsert']){
        unset($update_data[$field]);
      }
    }

    if($action == 'update'){
      $updatesql = "UPDATE $table SET ";
      $intermed = '';
      foreach($update_data as $field => $value){
        $esc_value = $this->escape_str($value);
        $updatesql .= "$intermed`$field` = '$esc_value'";
        $intermed = ', ';
      }

      $updatesql .= " WHERE `$key_field` = '$key_value'";

      if($this->db->update('sql:'.$updatesql)){
        $this->feedback = "Item successfully updated in $table table";
      }else{
        $this->exit_error($this->db->text_errors());
      }





    }else if($action == 'insert'){
      $insertsql = "INSERT INTO $table ";

      $intermed = '';
      foreach($update_data as $field => $value){
        echo $this->dbg('Auto Inc',$attribs['auto_increment']);
        if($attribs['auto_increment'] !== true || $value != ''){ // Don't try to insert blank strings for AI fields (duh!)
          $esc_value = $this->escape_str($value);
          $sqlfields .= "$intermed $field";
          $sqlvalues .= "$intermed '$esc_value'";
          $intermed = ',';
        }
      }
      $insertsql .= "($sqlfields) VALUES ($sqlvalues)";

      if($this->db->insert('sql:'.$insertsql)){
        $this->set_feedback("Item successfully added to $table table");
      }else{
        $this->exit_error('SQL error in INSERT'.$this->db->text_errors());
      }


    }

  }

  function set_session($var,$value){
    $_SESSION[$this->ses_name][$var] = $value;
  }

  function unset_session($var){
    unset($_SESSION[$this->ses_name][$var]);
  }

  function session($var){
    return $_SESSION[$this->ses_name][$var];
  }

  function set_field_position($table,$field,$position_type = "before",$relative_field = NULL){

    $temp_tbl = $this->struct[$table]['fields'][$field];
    $temp_fid = $this->fields[$table.$field];

    unset($this->struct[$table]['fields'][$field]);
    unset($this->fields[$table.$field]);

    if($position_type == "start"){
      $temp_fids = array_reverse($this->fields, true);
      $temp_fids[$table.$field] = $temp_fid;
      $this->fields = array_reverse($temp_fids, true);

      $temp_tbl_fields = array_reverse($this->struct[$table]['fields'], true);
      $temp_tbl_fields[$field] = $temp_tbl;
      $this->struct[$table]['fields'] = array_reverse($temp_tbl_fields, true);
    }

    if($position_type == "end"){
      $this->add_field($table,$field,$temp_tbl);
    }

    if($position_type && $relative_field){
      $this->struct[$table]['fields'] = $this->array_insert_assoc($this->struct[$table]['fields'],$relative_field,$field,$temp_tbl,$position_type);
      $this->fields = $this->array_insert_assoc($this->fields,$table.$relative_field,$table.$field,$temp_fid,$position_type);
    }

  }

  function add_field($table,$field,$attribs = array('type' => 'text')){
    if(!$attribs['title']){
      $attribs['title'] = ucfirst(trim($field));
    }
    if(!$attribs['fid']){
      $attribs['fid'] = $table.$field;
    }

    $attribs['table'] = $table;
    $attribs['field'] = $field;

    $this->set_field_attribs($table,$field,$attribs);
  }

  function set_field_attribs($table,$field,$attribs){
    foreach($attribs as $attrib => $value){
      $this->set_field_attrib($table,$field,$attrib,$value);
      $this->set_fid_attrib($table.$field,$attrib,$value);
    }
  }

  function set_field_attrib($table,$field,$attrib,$value){

  # [STRUCT FLAG]
      $this->struct[$table]['fields'][$field][$attrib] = $value;
      $this->set_fid_attrib($table.$field,$attrib,$value);
  }

  function set_fid_attrib($fid,$attrib,$value){
  # [STRUCT FLAG]
      $this->fields[$fid][$attrib] = $value;
  }

  function get_table_fields($table){
  # [STRUCT FLAG]
    return $this->struct[$table]['fields'];
  }
  function get_table_info($table){
  # [STRUCT FLAG]
    return $this->struct[$table];
  }
  function get_primary_table(){
    # [STRUCT FLAG]
    foreach ($this->struct as $table=>$attribs) {
      if($attribs['type'] == 'primary'){
        return $table;
      }
    }
  }

  function get_primary_key($table = NULL){
    if(!$table){
      $table = $this->get_primary_table();
    }
    # [STRUCT FLAG]
    foreach($this->struct[$table]['fields'] as $field => $attribs){
      if($attribs['key']=='primary'){
        return $field;
      }
    }
  }

  function get_tables(){
  # [STRUCT FLAG]
    return $this->struct;
  }

  function remove_field($table,$field){
     # [STRUCT FLAG]
    unset($this->struct[$table]['fields'][$field]);
    unset($this->fields[$table.$field]);
  }

  function add_parent_table($table,$local_key,$foreign_key,$display_field = null){

    # [STRUCT FLAG]

    if(!$display_field) $display_field = $foreign_key;
    $primary_table = $this->get_primary_table();
    $this->struct[$table]['local_key'] =  $local_key;
    $this->struct[$table]['foreign_key'] =  $foreign_key;
    $this->struct[$table]['type'] = 'parent';

    // set the local key to a dynamic select
    $this->struct[$primary_table]['fields'][$local_key]['type'] = "select:$table,$foreign_key,$display_field";

    // Guess at the singular title of this table's items
    if(substr($table,-3) == 'ies'){
      $column_title = substr($table,0,-3).'y';
    }elseif(substr($table,-1) == 's'){
      $column_title = substr($table,0,-1);
    }else{
      $column_title = $table;
    }

    $column_title = ucfirst(str_replace('_',' ',$column_title));

    // Hide the (probably numeric) local key so it doesn't show in the grid
    $this->struct[$primary_table]['fields'][$local_key]['display_type'] = "none";

    //Get the possible values (added 2016-12 for Ajax purposes)
    $options_rows = $this->db->select_all($table,$foreign_key);
    foreach ($options_rows as $opt_key => $opt_row){
      $options[$opt_key] = $opt_row[$display_field];
    }

    $this->struct[$primary_table]['fields'][$local_key]['options'] = $options;

    // Show the foreign title field, with appropriate (local field) header
    $this->struct[$table]['fields'][$display_field] = array(
                  'type'=>'text',
                  'title'=>$column_title,
                  'fid' => $table.$display_field,
                  'relation' => 'parent_secondary', // Relation values added 2016-12-07, used in grid_template for setting up Ajax edit for parent table values (in parent record title column). Don't like this. It's too ad hoc. Need a better, more consistent way of notating table and field relations...
                  'relation_key_field' => $foreign_key,
                  'relation_table' => $table,
                  'table' => $table,
                  'field' => $display_field
                  );

    $this->fields[$table.$display_field] = $this->struct[$table]['fields'][$display_field];
    $this->fields[$primary_table.$local_key] = $this->struct[$primary_table]['fields'][$local_key];



  }

  function add_child_table($table,$parent_link_field,$child_link_field = NULL,$fields = null){
    $this->struct[$table]['parent_link_field'] =  $parent_link_field;
    $clf = $child_link_field ? $child_link_field : $parent_link_field;

    $this->struct[$table]['child_link_field'] =  $clf;
    $this->struct[$table]['type'] = 'child';
    $this->struct[$table]['display_type'] = 'popup';
    $this->struct[$table]['title'] = ucfirst($table);
    $this->struct[$table]['GET_pfx'] = 'dg'.substr(md5($table),0,5).'_';
    if($fields){
     # [STRUCT FLAG]
      $this->struct[$table]['fields'] = $fields;
    }else{
      $this->auto_detect_fields($table,'child');
    }
  }

  function field_attribs($arg1,$arg2 = NULL){
    if($arg2){ // Field and table
        return $this->struct[$arg1]['fields'][$arg2];
    }else{     // Find by FID
        return $this->fields[$arg1];
    }
  }

  function field_attrib($fid,$attrib){
    return $this->fields[$fid][$attrib];
  }

  function auto_detect_fields($table,$type='primary'){

    $table_description = $this->db->select("sql:DESCRIBE $table");

    if(!$table_description){
      $this->exit_error('Database error in auto_detect_fields(). <b>Please make sure your table names are correct in main and child table related code.</b><br /><br />Database errors:<br />'.$this->db->text_errors());
    }

    foreach ($table_description as $key=>$row){

      $attribs = array();

      $attribs['maxlength'] = substr(strrchr($row['Type'], "("), 1, -1);
      if(strpos($row['Type'],"(") !== false){
        $attribs['db_type'] = substr($row['Type'],0,strpos($row['Type'],"("));
      }else{
        $attribs['db_type'] = $row['Type'];
      }

      if($row['Extra'] == 'auto_increment'){
        $attribs['auto_increment'] = true;
      }

      $name = $row['Field'];

      if($row['Key'] == 'PRI'){
        $attribs['type'] = "readonly,hidden";
        $attribs['key'] = 'primary';

        $this->struct[$table]['primary_key_field'] = $name;

        if(substr($name,-2) == "id"){
           $attribs['title'] = 'ID';
        }

        if($this->primary_field == ''){
          $this->primary_field = $row['Field'];
        }
        if($this->primary_table == ''){
          $this->primary_table = $table;
        }
      }elseif(strpos($row['Type'],'text') !== false){
        $attribs['type']= "textarea";
        $attribs['key']= NULL;
      }else{
        $attribs['type'] = "text";
        $attribs['key']= NULL;
      }


      if($row['Field'] == 'content'){
        $attribs['type'] = 'wysiwyg';
      }elseif($row['Field'] == 'date'){
        $attribs['type'] = 'date';
      }

      if(!$attribs['title']){
        $attribs['title'] = str_replace('_',' ',ucfirst($row['Field']));
      }

      $attribs['fid'] = $table.$name;

      $attribs['table'] = $table;
      $attribs['field'] = $name;

      $this->fields[$table.$name] = $attribs;

      $this->struct[$table]['fields'][$row['Field']] = $attribs;
      $this->struct[$table]['type'] = $type;
      $attribs['table'] = $table;
    }
  }


  function grid_query(){

      $sql = 'SELECT ';

      $db_fields = array();
      $db_tables = array();

      foreach ($this->get_tables() as $table => $tabledata){
        if($tabledata['type'] != 'child'){
          $db_tables[$table] = $tabledata;

          if($tabledata['type'] == 'primary'){
            $primary_table = $table;
          }


          $table_fields = $this->get_table_fields($table);

          if(!is_array($table_fields)){
            $this->exit_error('Invalid table: '.$table);
          }

          foreach($table_fields as $field => $attribs){
            if ($attribs['display_type'] != 'noquery' && !$attribs['noquery'] && $attribs['type'] != 'derivative'){
              // Add alias for fields in non-primary table to avoid name conflicts
              if($table != $primary_table){
                 $alias_sql = ' as '.$table."_".$field;
              }else{
                 $alias_sql = '';
              }

              if(!$this->check_hidden_field($attribs['fid'])){
                  $db_fields[] = '`'.$table.'`'.'.'.'`'.$field.'`'.$alias_sql;
              }
            }
          }
        }
      }

      $sql .= join($db_fields,', ');

      $sql .= " FROM ";
      $count = '';
      foreach($db_tables as $table => $table_attribs){

        if($table_attribs['type'] == 'primary'){
          $sql .= "$table ";
        }else if($table_attribs['type'] == 'parent'){
          $sql .= " JOIN $table ON ".$table.'.'.$table_attribs['foreign_key']." = ".$primary_table.'.'.$table_attribs['local_key'];
        }
      }

      $this->totalrows = $this->db->count('sql:'.$sql);


      if ($this->searchsql && $this->detailssql){
        $sql .=" WHERE  ".$this->searchsql." AND ".$this->detailssql;
      }elseif($this->searchsql){
        $sql .=" WHERE  ".$this->searchsql;
      }elseif($this->detailssql){
        $sql .=" WHERE  ".$this->detailssql;
      }

      $extra_sql_conditions = $this->setting('sql_conditions');

      if(is_array($extra_sql_conditions)){
         if(count($extra_sql_conditions) > 0){
           if($this->searchsql || $this->detailssql){
              $sql .=" AND ";
           }else{
              $sql .=" WHERE ";
           }

           $sql .= join(' AND ',$extra_sql_conditions);

         }
      }



      $this->queriedrows = $this->db->count('sql:'.$sql);

      if ($this->sortfield){
        $sql .= " ORDER BY ".$this->sorttable.".".$this->sortfield." ".$this->sorttype;
      }

      if ($this->limit){
        $sql .= " LIMIT ".$this->start.", ".$this->limit;
      }

      $this->displayedrows = $this->db->count('sql:'.$sql);

      $this->sql = $sql;


      $this->raw_grid_data = $this->db->select('sql:'.$this->sql);
  }

  function toggle_sorttype($sorttype){
    if($sorttype == 'ASC'){
      return 'DESC';
    }else{
      return 'ASC';
    }
  }

  function grid(){
    // Delete grid-related GET items from the target URL
    $ugp = $this->settings['unique_GET_prefix'];

    foreach ($this->target_url->query_parts as $key=>$value) {
    	if(substr($key,0,strlen($ugp)) == $ugp){
        $this->target_url->delete_query_pair($key);
      }
    }

    # [DETAILS FLAG]
    if($this->GET('mode') == 'child'){
      $this->setup_child_grid();
    }

    $out = '';

    if ($this->POST('action') == 'update' || $this->POST('action') == 'insert'){
      if($this->check_privilege('edit')){
        $this->process();
      }
    }

    if($this->GET('action') == 'delete'){
      if($this->check_privilege('delete')){
        $this->set_feedback($this->item_delete($_GET));
      }
    }

    if ($this->GET('hide_field')){
      $this->hide_field($this->GET('hide_field'));
    }

    if ($this->GET('unhide_field')){
      $this->unhide_field($this->GET('unhide_field'));
    }

    if($this->GET('action') == 'export_csv'){
      $out .= $this->csv_export_options();
    }else if($this->GET('action') == 'html_form_options'){
      $out .= $this->html_form_options();
    }else if($this->GET('action') == 'create_html_form'){
      $out .= $this->create_html_form();
    }else if($this->GET('action') == 'create_php_code'){
      $out .= $this->create_php_code();
    }else if($this->GET('action') == 'process_csv'){
      $this->csv_export_process();
    }

    else if ($this->GET('action') == 'edit' || $this->GET('action') == 'add' || $this->GET('action') == 'copy'){
      if($this->check_privilege('add') && ($this->GET('action') == 'add' || $this->GET('action') == 'copy')){
        $out .= $this->edit();
      }
      if($this->check_privilege('edit') && $this->GET('action') == 'edit'){
        $out .= $this->edit();
      }
    }elseif($this->GET('action') == 'report'){

      $out = $this->report($this->GET('report'));


    }elseif($this->GET('action') == 'ajax_save'){

      $this->ajax_save();

    }else{

      $this->sort();
      $this->search();
      $this->paging();
      $this->grid_query();
      $this->default_hidden_fields();
      $this->process_grid_data();

      $out .= $this->feedback();
      $out .= $this->grid_controls();
      $out .= $this->hidden_fields_links();
      $out .= $this->rowcount_feedback();
      $out .= $this->grid_template();
      $out .= $this->paging_results_and_links();
      $out .= $this->admin_tools();
      $out .= $this->dev_tools();
      $out .= $this->footer();
      $out .= $this->js_fields_object();

      $this->dbg('Struct',$this->struct);
      $this->dbg('SESSION',$_SESSION);

    } /**//**/

    $return = '';

    if ($this->mode_shows('html_head')){
       $return .= $this->html_header();
    }
    if ($this->mode_shows('title')){
       $return .= $this->grid_title();
    }


    if($this->setting('mode') == 'child' && $this->GET('display_type') == 'popup'){
      $return .= '<div id="close_window_button" onClick="window.close();">Close window</div>';
    }


    $return .= $out;

    return $return;
  }

  function edit_form($id){

    $img_path = $this->paths['images_path'];

    $this->dbg('Target URL in edit_form()',$this->target_url->get());

    $out = "
    <div align=\"center\" class=\"dg_edit_buttons\">";
    $keyinput = "<input type=\"hidden\" name=\"".$this->GET_pfx()."id\" value=\"$id\" />";


    if(strpos($this->settings['privileges'],'edit') !== false){
      $out .= "
      <form name=\"update\" action=\"".$this->target_url->get()."\" method=\"get\" class=\"update\">";
      $out .= $this->target_url->get_hidden_inputs();
      $out .= "
      <input type=\"hidden\" name=\"".$this->GET_pfx()."action\" value=\"edit\" />
      <!-- input type=\"submit\" value=\"a\" class=\"update icon-edit\"  title=\"Edit record\" alt=\"Edit record\" -->
      <input type=\"image\" src=\"".$img_path."edit-icon-20px.png\" title=\"Edit record\" alt=\"Edit\" />
      $keyinput
      </form>
      ";
    }

    if(strpos($this->settings['privileges'],'add') !== false){
      $out .= "
      <form name=\"update\" action=\"".$this->target_url->get()."\" method=\"get\" class=\"update\">";
      $out .= $this->target_url->get_hidden_inputs();
      $out .= "
      <input type=\"hidden\" name=\"".$this->GET_pfx()."action\" value=\"copy\" />
      <!-- input type=\"submit\" value=\"c\" class=\"icon-new copy\" title=\"Copy record\"/ -->
      <input type=\"image\" src=\"".$img_path."copy-icon-20px.png\" title=\"Copy record\" alt=\"Copy\"  />
      $keyinput
      </form>
      ";
    }

    if(strpos($this->settings['privileges'],'delete') !== false){
      $out .= "<form action=\"".$this->target_url->get()."\" method=\"get\" class=\"update\">";
      $out .= $this->target_url->get_hidden_inputs();
      $out .= "
      <input type=\"hidden\" name=\"".$this->GET_pfx()."action\" value=\"delete\" />
      $keyinput

      <!--input type=\"submit\" value=\"b\" class=\"delete icon-delete\" onClick=\"return confirmSubmit('$id')\"  title=\"Delete record\"/ -->

      <input type=\"image\" src=\"".$img_path."delete-icon-20px.png\" title=\"Delete record\" onClick=\"return confirmSubmit('$id')\" alt=\"Delete\"  />
      <!-- input type=\"submit\" value=\"Del\" class=\"delete\" onClick=\"return confirmSubmit('$id')\"/ -->
      </form>";
    }


    $out .= "</div>";
    return $out;
  }

  function start_row($keyval,$oddrow){
    if($oddrow == 1){
      $row_class = "odd_row";
    }else{
      $row_class = "even_row";
    }
  return "<tr id=\"".$this->rowcount."\" class=\"$row_class\" onClick=\"highlightRow('".$this->rowcount."','$row_class')\">";
  }

  function edit(){

    $non_array_input_count = 0;

    $redir_page = $this->GET('redir_page');

    $action = $this->GET('action');

    if($action == 'add' || $action == 'copy'){
       $nextaction = 'insert';
    }elseif($action == 'edit'){
       $nextaction = 'update';
    }

    $id = $key_value = $this->GET('id');

    $key_field = $this->get_primary_key($this->primary_table);

    // Turn the GET array into a string for the save and continue var
    $get_string = "";
    $int = '';

    foreach ($_GET as $key=>$value) {
      $get_string .= $int.$key."=".$value;
      $int = '&';
    }

    $table = $this->get_primary_table();
    $table_attribs = $this->get_table_info($table);
    $fields = $this->get_table_fields($table);

    $out  = $this->feedback();

    $out .= "<form action=\"".$this->target_url->get(array($this->GET_pfx().'action' => null))."\" method=\"post\"  enctype=\"multipart/form-data\" id=\"edit_form\">
    <div>
    ";

    if($action == 'edit' || $action == 'copy'){
      $row = $this->db->select_one("sql:".$this->edit_query($id));
      $this->edit_data = $row;
    }else{
      $row = NULL;
    }

    if($action == 'copy'){
      unset($row[$key_field]);
    }


    foreach ($fields as $field => $fa){
      $fieldattribs = $fa;

      // Backwards compatible update to attribute name from 'type' to 'edit_type' (2015-12-16)

      if($fa['edit_type']){
        $fa['type'] = $fa['edit_type'];
      }

      if(strpos($fa['type'],':') !== false){
        $type_parts = explode(':',$fa['type']);
        $type = $type_parts[0];
        $type_params = explode(',',$type_parts[1]);
      }else{
        $type = $fa['type'];
        unset($type_params);
      }

      $break = "<br clear=\"all\" /></div>";

      if(!$type){

        $this->exit_error('Field type is empty for: '.$field);

      }elseif($type != 'derivative' && $type != 'none'){
      // Added 'none' option for fields that shouldn't appear on edit page at all (2015-12-16)

        if ($fa['default'] && ($row[$field]=='')){
            $row[$field] = $fa['default'];
        }

        if ($fa['maxlength']){
            $maxlength = "
            <span class=\"maxlength\">&nbsp;&nbsp; (Max $fa[maxlength] characters)</span>
            ";
        }

        if ($fa['edit_style']){
            $style = "style=\"$fa[edit_style]\" ";
        }else{
            $style = "";
        }
        if ($fa['edit_class']){
            $class = "class=\"$fa[edit_class]\" ";
        }else{
            $class = "";
        }
        if ($fa['edit_element_id']){
            $element_id = "id=\"$fa[edit_element_id]\" ";
        }else{
            $element_id = " id=\"edit_$field\" ";
        }

          $fieldlabel = "
          <div class=\"fielddiv\" id=\"$table$field\">
          <span class=\"label\">$fa[title]:</span>
          ";

          // CALLBACK FIELDS
          if($fa['edit_callback']){
             $function = $fa['edit_callback'];
             if(is_callable($function)){
                $out .= $fieldlabel;
                $out .= $function($this->get_primary_table(),$field,$row[$field],$row,$id);
             }else{
                $this->dbg('Undefined or inaccessible callback function:',$function,'error');
             }

          }
          //READ ONLY FIELDS
          elseif ($type == 'readonly'){
            $out .= "
            $fieldlabel
            <span class=\"readonly\"$style>$row[$field]</span>
            ";
          }

          //HIDDEN FIELDS
          else if($fa['type'] == 'hidden'){
            $out .= "<input type=\"hidden\" name=\"data[$field]\" value=\"$row[$field]\" $element_id/>";
            $break = '';
          }

          //READ ONLY + HIDDEN FIELDS
          else if ($type == 'readonly,hidden'){
            $out .= "
            $fieldlabel <span class=\"readonly\"$style>$row[$field]</span>
            <input type=\"hidden\" name=\"data[$field]\" value=\"$row[$field]\" $element_id/>
            ";
          }

          //TEXT FIELDS
          else if ($type == 'text'){
            $out .= "$fieldlabel
            <input type=\"text\" name=\"data[$field]\" value=\"$row[$field]\" $fieldclass maxlength=\"$fa[maxlength]\" $style $element_id/> $maxlength
            ";
          }

          //DYNAMIC SELECT FIELDS
          else if ($type == 'select'){
            $table = $type_params[0];
            $value_field = $type_params[1];
            $display_field = $type_params[2];
            if($type_params[3]){
              $add_blank_field = true;
            }else{
              $add_blank_field = false;
            }
            $add_blank_field = $type_params[3];
            $out .= $fieldlabel;
            $out .= $this->dynamic_select("data[$field]", $table, $value_field, $display_field,$row[$field],$add_blank_field);
          }

          //TEXTAREA FIELDS
          else if ($type == 'textarea'){
            $out .="
            $fieldlabel
            <textarea name=\"data[$field]\"  $fieldclass $style $element_id>$row[$field]</textarea> $maxlength";
          }

         //EXTRA FIELDS
         else if ($type == 'extra'){
            $out .= $type_params[0];
         }

         //WYSIWYG TEXTAREA FIELDS
         else if ($type == 'wysiwyg'){
            $size_array = $type_params;
            if(count($size_array) < 1){
              $size_array = array('900','500');
            }

            $init_str = "?content_css=".$this->settings['content_css']."&width=$size_array[0]&height=$size_array[1]&version=".$this->settings['tmce_version'];

            if($this->settings['tmce_version'] == 'new'){
              $this->add_head_link('tinymce.min.js','js','//tinymce.cachefly.net/4.2/');
            }else{
              $this->add_head_link('tiny_mce.js','js',$this->paths['tiny_mce_path']);
              $this->add_head_link('plugins/tinybrowser/tb_tinymce.js.php','js',$this->paths['tiny_mce_path']);
            }
            $this->add_head_link('tinymce_init.js.php'.$init_str,'js',$this->paths['js_path']);

            $out .= "
            $fieldlabel
            <div class=\"dg-tinymce-container\">
            <textarea  name=\"data[$field]\"
            class=\"mceEditor\" id=\"mceEditor\">$row[$field]</textarea>
            $maxlength </div>
            ";
         }

          //STATIC SELECT FIELDS
         else if ($type == 'staticselect'){
            $statselect_items = $type_params;
            $out .= "$fieldlabel<select name=\"data[$field]\"  $fieldclass $element_id><br />";
            $current_value_listed = false;
            foreach ($statselect_items as $key => $statselect_item){
                  $out .= "<option";
                  if ($statselect_item == $row[$field]){
                  $out .= " selected ";
                    $current_value_listed = true;
                  }
                  $out .= ">$statselect_item</option>";
            }
            // Avoid forcing overwrite of values not in list on edit...
            if(!$current_value_listed){
               $out .= "<option selected>".$row[$field]."</option>";
            }

            $out .= "</select>
            ";
          }

          //DATE FIELDS
          else if ($type == 'date'){

            $out .= "<input type=\"hidden\" name=\"non_array_input_names[$non_array_input_count]\" value=\"non_array_$field\" $element_id/>";

            $non_array_input_count++;

            if($type_params[0]){
              $dateFormat = $type_params[0];
            }else{
              $dateFormat = 'YYYY-MM-DD';
            }
            if($row[$field]){
              $defaultDate = $row[$field];
            }else{
              $defaultDate = date('Y-m-d');
            }

            if($fa['required']){
              $requiredDate = true;
            }else{
              $requiredDate = false;
            }

            $out .= "$fieldlabel<script>DateInput('non_array_$field', '$requiredDate', '$dateFormat','$defaultDate')</script>";
          }

          //PASSWORD FIELDS (added Dec 15 09)
          else if ($type == 'password'){
            if($type_params[0]){
              $hashtype = $type_params[0];
            }

            if($row[$field] && $hashtype){
              $passwd_code = 'Password is a '.$hashtype.' hash. Reset the password in the DB admin if necessary';
            }else{
              $passwd_code = "<input type=\"password\" name=\"data[$field]\" value=\"$row[$field]\" $fieldclass maxlength=\"$fa[maxlength]\" $element_id/> $maxlength";
            }

            $out .= "$fieldlabel $passwd_code";
          }


          // MULTIPLE CHECKBOX FIELDS

          else if ($type == 'multicheckbox'){
            $options = $type_params;

            $mc_code = "<table><tr><td>";

            $current_values = explode(', ',$row[$field]);

            foreach ($options as $key=>$value) {

              if(in_array($value,$current_values)){
                $checked = 'checked';
              }else{
                $checked = '';
              }

            	$mc_code .= '<div><div style="clear: left; width:20px; height: 20px; float:left; padding-top: 5px;">';
              $mc_code .= "<input style=\"width: 20px;\" type=\"checkbox\" id=\"dg_multicheck_".$field."_".$value."\" onChange=\"multicheckUpdate('dg_multicheck_$field','$value')\" $checked/>";
              $mc_code .= '</div><div style="height: 20px; float:left; padding-left: 10px; padding-bottom: 5px;">';
              $mc_code .= '<label for="dg_multicheck_'.$field.'_'.$value.'">'.$value.'</label>';
              //
              $mc_code .= "</div></div>";

            }

            $mc_code .= "<br clear=\"all\"><div style=\"margin-top:20px;\"><textarea style=\"width: 400px; height:50px;\" id=\"dg_multicheck_$field\" name=\"data[$field]\">$row[$field]</textarea></td></tr></table>";

            $mc_code .= "";
            $mc_code .= '</td></tr></table>';

            $out .= "$fieldlabel $mc_code";
          }

          // ACCESS CONTROL FIELDS

          else if ($type == 'access_control'){

            if($type_params[0]){
              $default = $type_params[0];
            }

            // Check if there's a custom access control edit function available
            if(function_exists('dg_edit_access_control')){
              $out .= dg_edit_access_controls($table,$keyval,$default);
            }else{
              $out .= $this->dg_edit_access_controls($table,$keyval,$default);
            }
          }else{
            $this->exit_error('Invalid field type: "'.$type.'"');
          }


          if($fa['notes']){
            $out .= "
            <div class=\"edit_note\">
            <b>Note:</b> $fa[notes]
            </div>";
          }
          $out .= $break;

          unset($maxlength);
        }
      }

    $out.= "
    <input type=\"hidden\" name=\"".$this->GET_pfx()."action\" value=\"$nextaction\">
    <input type=\"hidden\" name=\"redir_page\" value=\"$redir_page\">
    <input type=\"hidden\" name=\"key_field\" value=\"$key_field\">
    <input type=\"hidden\" name=\"key_value\" value=\"$key_value\">

    <br clear=\"all\">
    <div>";



    if($action == 'edit'){
      $out.= "
<button type=\"submit\" class=\"dg_edit\">Save Changes</button>";

      if(strpos($_SERVER['HTTP_USER_AGENT'],'MSIE') !== false){
        $out.= "
<button type=\"submit\"  class=\"dg_edit\" id=\"continue_edit_button\" onClick=\"contButton('on')\">Save and continue editing</button>
<input type=\"hidden\" name=\"continue\" id=\"continue_edit\" value=\"\">
        ";
      }else{
        $out.= "
<button type=\"submit\" name=\"continue\" value=\"on\" class=\"dg_edit\">Save and continue editing</button>";
      }

    }else{
      $out .= "
<button type=\"submit\" class=\"dg_edit\">Save</button>";
    }


    $out .= "
<button type=\"button\" onclick=\"javascript:location.href ='".$this->target_url->get(array('action'=>NULL))."'\" class=\"dg_edit\">Cancel</button>
    </div>
    </div>
    </form><br />
 </body>
</html>";
    return $out;
  }

  function edit_query($id){

    // Start the query var
    $sql = 'SELECT ';

    // Write the fields to the query, with apostrophes
    $intermed = '';
    foreach($this->get_table_fields($this->primary_table) as $field => $attribs){
      if ($attribs['type']!='derivative' && !$attribs['noquery']){
        $sql .= $intermed.'`'.$this->primary_table.'`'.".".'`'.$field.'`';
        $intermed = ', ';
      }
    }

    $sql .= " FROM ".$this->primary_table;
    $sql .= " WHERE ".$this->get_primary_key($this->primary_table)." = '$id'";
    return $sql;
  }



  function html_header(){
   $code = "<!DOCTYPE HTML PUBLIC \"-//W3C//DTD HTML 4.01 Transitional//EN\">
  <html>
    <head>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=windows-1250\">";

    $code .= $this->html_head_links();

    $code .= "<title>".$this->setting('display_name')."</title>
    </head>
    <body class=\"datagrid\">";

    return $code;
  }

  function html_head_links(){
     $code = '';
    // Merge built in links (no path) with user links (with path)
    $head_urls = $this->user_head_links;

    foreach ($this->head_links['css'] as $key=>$link){
      $head_urls['css'][] = $this->paths['css_path'].$link;
    }
    foreach ($this->head_links['js'] as $key=>$link){
      $head_urls['js'][] = $this->paths['js_path'].$link;
    }


    foreach ($head_urls['css'] as $key => $link){
        $code .= "<link href=\"".$link."\" rel=\"stylesheet\">\n";
    }

    foreach ($head_urls['js'] as $key => $link){
         $code .= "<script language=\"javascript\" type=\"text/javascript\" src=\"".$link."\"></script>\n";
    }

    return $code;
  }


  function grid_title(){
    $out = "<h1 class=\"datagrid_header\">".$this->setting('display_name')."</h1>";

    return $out;
  }

  function set_feedback($message,$class = "notice"){
    $this->feedback = $message;
    $this->feedback_class = $class;
  }

  function feedback(){
    if($this->feedback){
      $out = "<div id=\"feedback\" class=\"".$this->feedback_class."\">\n";
      $out .= $this->feedback;
      $out .= "\n</div>";
      return $out;
    }
  }
  function unhide_field($fid){
   $hidden_fields = $this->session('hidden_fields');
   unset($hidden_fields[$fid]);
   $this->set_session('hidden_fields',$hidden_fields);
  }
  function hide_field($fid){
    if($this->fields[$fid]['display_type'] != 'none'){
      $hidden_fields = $this->session('hidden_fields');
      $hidden_fields[$fid] = $this->fields[$fid]['title'];
      $this->set_session('hidden_fields',$hidden_fields);
    }
  }
  function check_hidden_field($fid){
    $hidden_fields = $this->session('hidden_fields');
    if(is_array($hidden_fields)){
     if($hidden_fields[$fid]){
       return true;
     }
    }
  }
  function hidden_fields_links(){
   $hidden_fields = $this->session('hidden_fields');
   if(is_array($hidden_fields) && count($hidden_fields) != 0){
     $out = '<div class="hidden_field_control">Show hidden fields: ';
     $intermed="";
    foreach($hidden_fields as $fid => $title){
       $out .= "$intermed<a href=\"".$this->target_url->get(array($this->GET_pfx()."unhide_field"=>$fid))."\">$title</a>";
        $intermed = " | ";
    }
    $out .= "</div>";
   }
   return $out;
  }

  function csv_export_options(){
    $out = "<h2>CSV export options</h2>";
    $out.= "<form name=\"csv_export\" action=\"".$this->target_url->get()."\" method=\"get\">";
    $out .= $this->target_url->get_hidden_inputs();
    $out .="Choose fields to include<br />";
    foreach($this->fields as $fid => $atrbs){
        if(!$this->check_hidden_field($fid)){
            $checked = 'checked';
        }else{
            $checked = '';
        }
        $out .= "<input type=\"checkbox\" name=\"".$this->GET_pfx()."fields[$fid]\" $checked /> $atrbs[title]<br />";
    }
    $out .= "<input type=\"hidden\" name=\"".$this->GET_pfx()."action\" value=\"process_csv\">";
    $out .= "<input type=\"submit\"> <button type=\"button\" onclick=\"javascript:location.href ='".$this->url."'\">
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Cancel &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</button>";
    $out .= "</form>";
    return $out;
  }

  function csv_export_process(){

    $filename = str_replace(' ','_',$this->setting('display_name')).'_export_'.date('Y-m-d',time()).'.csv';

    $fields = $this->GET('fields');

    foreach($this->fields as $fid => $attribs){
      if($fields[$fid] != 'on'){
        $this->hide_field($fid);
      }else{
        $this->unhide_field($fid);
      }
    }

    $this->grid_query();

      $csv_terminated = "\n";
      $csv_separator = ",";
      $csv_enclosed = '"';
      $csv_escaped = "\\";

      $data = $this->raw_grid_data;

      $csv_headers = array();

      foreach ($data[0] as $key=>$value) {
       $csv_headers[] = $key;
      }


      $d_rev = array_reverse($data, true);
      $d_rev[] = $csv_headers;
      $data = array_reverse($d_rev, true);

      foreach ($data as $key=>$row) {
        $sep = '';
        foreach ($row as $field=>$value) {
          $value = strip_tags($value);
          $out .= $sep.$csv_enclosed;
          $out .= str_replace($csv_enclosed, $csv_escaped.$csv_enclosed,$value);
          $out .= $csv_enclosed;

          $sep = $csv_separator;

        }
        $out .= $csv_terminated;
      }

      if($this->modes[$this->setting('mode')]['redirect_file_exports']){

        $this->set_session('csv_data',$out);

        $token = $this->get_sub_token('csv');

        $get_str = "?dg_id=".$this->unique_dg_id."&ses_key=csvsub_token&token=$token&csv_fn=$filename";


        $csv_url = $this->paths['dg_files_path'].'csv_export.php';

        echo "<div class=\"download_button\"><a href=\"$csv_url".$get_str."\">Download CSV</a></div>";

      }else{

        /**/
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Content-Length: " . strlen($out));
        header("Content-type: text/x-csv");
        //header("Content-type: text/csv");
        //header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=$filename");

        echo $out;
        exit;


      }




   }

  function get_sub_token($session_key = ''){
    $token = md5($this->dg_name.time().$this->secure_salt);
    $session_token = md5($token.$this->secure_salt);
    $session_key .= 'sub_token';
    $this->set_session($session_key,$session_token);

    $this->dbg('Making sub token w ses key',$session_key);
    $this->dbg('$token',$token);
    $this->dbg('$session_token',$session_token);
    $this->dbg('$session_key',$session_key);

    return $token;
  }

  function csv_export_link(){

    $url = $this->target_url->get($this->GET_pfx()."action=export_csv");
    return "<br /><a href=\"$url\">Export CSV</a>";
  }

  function html_form_link(){
    return "<br /><a href=\"".$this->urlqs."action=create_html_form\">Create PHP/HTML form</a>";
  }
  function php_input_link(){
    return "<br /><a href=\"".$this->urlqs."action=create_php_code\">Create PHP input code</a>";
  }

  function dynamic_select($name, $options_table, $opt_value_field, $opt_display_field = NULL, $current_value = NULL,$blank_option = NULL){



    $options = $this->db->select_all($options_table);

    if($blank_option){
      array_unshift($options, array(
        $opt_value_field => '',
        $opt_display_field => ''
        ));

    }

    $out = "<select name=\"$name\" size=\"1\">
    ";
    foreach ($options as $key=>$row) {
      $out .= "<option ";
      if ($row[$opt_value_field] == $current_value){
        $out .= "selected ";
      }
      $out .= "value=\"$row[$opt_value_field]\">".$row[$opt_display_field]."</option>";
    }
    $out .= "</select>
    ";
    return $out;
  }

  function default_hidden_fields(){
    if(!$this->session('hidden_fields') && !$this->GET('unhide_field')){
      if($def_hid_fields = $this->settings['default_hidden_fields']){
        foreach ($def_hid_fields as $key=>$fid) {
          $this->hide_field($fid);
        }
      }
    }
  }

  function check_privilege($p){
    if(strpos($this->setting('privileges'),$p) !== false){
      return true;
    }
  }

  function set_table_attribs($table,$attribs){
    if(is_array($attribs)){
      foreach ($attribs as $key=>$value) {
      	$this->set_table_attrib($table,$key,$value);
      }
    }
  }

  function set_table_attrib($table,$key,$value){
    #[STRUCT FLAG]
    $this->struct[$table][$key] = $value;
  }

  function mode_shows($item = NULL){
    if($item){
      return $this->modes[$this->setting('mode')]['show_'.$item];
    }else{
      return $this->modes[$this->setting('mode')];
    }
  }

  private function admin_tools(){
    if($this->mode_shows('admin_tools')){
      $out .= $this->csv_export_link();
      return $out;
    }
  }

  private function dev_tools(){
    if($this->mode_shows('dev_tools')){
      $out .= $this->html_form_link();
      $out .= $this->php_input_link();
      return $out;
    }
  }

  private function show_child_grid($table,$link_value){
    if($this->struct[$table]['edit_url']){
       $child_url = $this->struct[$table]['edit_url'].'?';
     }else{
       $database_name = $this->db->db_name;
       $child_url = $this->dg_relative_path.'prototype_child.php?';
       $child_url .= "token=".$this->get_sub_token()."&dg_id=".$this->unique_dg_id."&";
       $child_url .= 'table='.$table.'&database='.$database_name.'&';
     }

     $GET_pfx = $this->struct[$table]['GET_pfx'];
     $parent_link_field = $this->struct[$table]['parent_link_field'];
     $child_link_field = $this->struct[$table]['child_link_field'];
     $child_title = $this->struct[$table]['title'];
     $display_type = $this->struct[$table]['display_type'];

     testit('$table in show_child_grid',$table);

    $out .= '
    <tr id="child_grid_'.$table.'_'.$link_value.'" class="child_grid" style="display: none;">
    ';


    /* This code is now added via JS when the link is clicked... keeping it here for now just in case
    $out .=  '
    <td colspan="100">
    <iframe src="'.$child_url.$GET_pfx.'mode=child&'.$GET_pfx.'parent_link_field='.$parent_link_field.'&'.$GET_pfx.'child_link_field='.$child_link_field.'&'.$GET_pfx.'link_value='.$link_value.'&'.$GET_pfx.'display_type='.$display_type.'" style="width: 100%; height: 400px; border: 0px;" seamless/>
    </iframe>
    </td>';
    */

    $out .=  '
    </tr> ';
    return $out;
  }

  function html_form_options(){
    $out = "<h1>HTML form options</h1>";
    $out .= "<form action=\"".$this->url."\" method=\"GET\">
    <input type=\"hidden\" name=\"action\" value=\"create_html_form\">
    Action:<br /><input type=\"text\" name=\"form_options[action]\"><br /><br />\n";
    $out .= "
    Method:<br />
    <input type=\"radio\" value=\"POST\" name=\"form_options[method]\">POST    <br />
    <input type=\"radio\" value=\"POST\" name=\"form_options[method]\">GET     <br />
<br />

    <input type=\"checkbox\" checked name=\"include_php_refill\">Fill values from request
    <br /><br />
    <input type=\"checkbox\" checked name=\"use_forms_class\">Use forms class
    <br /><br />

    <input type=\"submit\" />
    </form>



    ";
    echo $out;
  }

  function create_html_form(){

    $table = $this->get_primary_table();
    $table_attribs = $this->get_table_info($table);
    $fields = $this->get_table_fields($table);

    $data = $this->GET('form_options');

    $code = form::begin($data['action'],$data['method']);

    unset($row[$key_field]);

    foreach ($fields as $field => $fieldattribs){

      if ($fieldattribs['type'] &&
          $fieldattribs['type'] != 'derivative' &&
          $fieldattribs['type'] != 'readonly' &&
          $fieldattribs['type'] != 'hidden' &&
          $fieldattribs['type'] != 'readonly,hidden'
          ){


          $code .= "
  <div class=\"form_field_container\" id=\"$table-$field\">
    <span class=\"label\">$fieldattribs[title]</span>
";

          //TEXT FIELDS
          if ($fieldattribs['type'] == 'text'){
          $code .= "    <input type=\"text\" name=\"data[$field]\" value=\"".'<?php echo $data['."'".$field."'".'];'.'?'.'>"'." maxlength=\"$fieldattribs[maxlength]\"/>";

          }

          //TEXTAREA FIELDS
          if (substr($fieldattribs['type'],0,8) == 'textarea'){
            $size_array = explode(',',substr($fieldattribs['type'],9));
            if($size_array[0]){
              $cols = "cols=$size_array[0] ";
            }
            if($size_array[1]){
              $rows = "rows=$size_array[1] ";
            }
            $code .="   <textarea name=\"data[$field]\" $cols$rows>".$this->php_tag('$_'.$data['method']."['".$field."']")."</textarea>";
          }

         // EXTRA FIELDS
         if (substr($fieldattribs['type'],0,6) == 'extra:'){
            $markup = substr($fieldattribs['type'],6);
            $out .= $markup;
         }

         // FUNCTION FIELDS function(table, field, value, row)
         if (substr($fieldattribs['type'],0,8) == 'function'){
           $function = substr($fieldattribs['type'],9);
           if(function_exists($function)){
              $out .= $fieldlabel;
              $out .= $function($this->get_primary_table(),$field,$row[$field],$row);
           }else{
              $this->dbg('Undefined and/or inaccessible callback function',$function);
           }
         }

          //STATIC SELECT FIELDS
          if (substr($fieldattribs['type'],0,13) == 'staticselect:'){
            $statselect_items = substr($fieldattribs['type'],13);
            $statselect_items2 = explode(',',$statselect_items);
            $out .= "$fieldlabel<select name=\"data[$field]\"  $fieldclass><br />";
            foreach ($statselect_items2 as $key => $statselect_item){
                  $out .= "<option";
                  if ($statselect_item == $row[$field]){
                  $out .= " selected ";
                  }
                  $out .= ">$statselect_item</option>";
            }
            $code .= "</select>
            ";
          }

          //DATE FIELDS
          if (substr($fieldattribs['type'],0,4) == 'date'){

            $code .= "<input type=\"hidden\" name=\"non_array_input_names[$non_array_input_count]\" value=\"non_array_$field\" />";

            $non_array_input_count++;

            if(substr($fieldattribs['type'],5)){
              $dateFormat = substr($fieldattribs['type'],5);
            }else{
              $dateFormat = 'YYYY-MM-DD';
            }
            if($row[$field]){
              $defaultDate = $row[$field];
            }else{
              $defaultDate = date('Y-m-d');
            }

            if($fieldattribs['required']){
            $requiredDate = true;
            }else{
            $requiredDate = false;
            }

            $code .= "$fieldlabel<script>DateInput('non_array_$field', '$requiredDate', '$dateFormat','$defaultDate')</script>";
          }

          // MULTIPLE CHECKBOX FIELDS

          if (substr($fieldattribs['type'],0,13) == 'multicheckbox'){
            $options = explode(',',substr($fieldattribs['type'],14));
            $mc_code = "<table><tr><td>"; // <table class=\"dg_multi_checkbox\" border=\"1\">";

            $current_values = explode(', ',$row[$field]);

            foreach ($options as $key=>$value) {

              if(in_array($value,$current_values)){
                $checked = 'checked';
              }else{
                $checked = '';
              }

            	$mc_code .= '<div><div style="clear: left; width:20px; height: 20px; float:left; padding-top: 5px;">';
              $mc_code .= "<input style=\"width: 20px;\" type=\"checkbox\" id=\"dg_multicheck_".$field."_".$value."\" onChange=\"multicheckUpdate('dg_multicheck_$field','$value')\" $checked/>";
              $mc_code .= '</div><div style="height: 20px; float:left; padding-left: 10px; padding-bottom: 5px;">';
              $mc_code .= '<label for="dg_multicheck_'.$field.'_'.$value.'">'.$value.'</label>';
              //
              $mc_code .= "</div></div>";

            }

            $mc_code .= "<br clear=\"all\"><div style=\"margin-top:20px;\"><textarea style=\"width: 400px; height:50px;\" id=\"dg_multicheck_$field\" name=\"data[$field]\">$row[$field]</textarea></td></tr></table>";

            $mc_code .= "";
            $mc_code .= '</td></tr></table>';

            $out .= "$fieldlabel $mc_code";
          }

        $code .= "
  </div>
";


        }



    }

    $code .= "
  <div class=\"form_submit_container\">
    <input type=\"submit\" />
  </div>
</form>";

    echo "<pre>".htmlspecialchars($code)."</pre>";

  }

  function php_tag($var_name){
    return "<?php echo $var_name; ".'?'.'>';
  }

  function GET($var = NULL){
    if($this->settings['unique_GET_prefix']){
      $pfx = $this->settings['unique_GET_prefix'];
    }

    if($var){
      return $_GET[$pfx.$var];
    }else{
      if(!$pfx){
        return $_GET;
      }else{
        foreach ($_GET as $key=>$value) {
          if(substr($key,0,strlen($pfx)) == $pfx){
            $key = substr($key,strlen($pfx));
            $out[$key] = $value;
          }
        }
        return $out;
      }
    }
  }

  function POST($var = NULL){
    if($this->settings['unique_GET_prefix']){
      $pfx = $this->settings['unique_GET_prefix'];
    }

    if($var){
      return $_POST[$pfx.$var];
    }else{
      if(!$pfx){
        return $_POST;
      }else{
        foreach ($_POST as $key=>$value) {
          if(substr($key,0,strlen($pfx)) == $pfx){
            $key = substr($key,strlen($pfx));
            $out[$key] = $value;
          }
        }
        return $out;
      }
    }
  }
  function set_GET($var,$value){
    if($this->settings['unique_GET_prefix']){
      $pfx = $this->settings['unique_GET_prefix'];
    }
    $_GET[$pfx.$var] = $value;
  }

  function GET_pfx(){
    if($this->settings['unique_GET_prefix']){
      return $this->settings['unique_GET_prefix'];
    }
  }

  function set_config($var,$val){
    $this->config[$var] = $val;
  }

  function escape_str($string){
    if (!get_magic_quotes_gpc()){
      return addslashes($string);
    }else{
      return $string;
    }
  }

  function dbg($var,$val,$status = 'DEBUG'){

    if(is_callable($this->setting('global_debug_function'))){

      $dbgf = $this->setting('global_debug_function');
      $dbgf($var,$val,$status);
    }else if($this->debug_level == 2 || ($this->debug_level == 1 && $status == 'error')){

       $out = "<b>[".strtoupper($status)."] $var:</b>";
       if (is_array($val)){
          $out .= "<pre>";
          $out .=  print_r($val,'true');
          $out .= "</pre>";
       }else{
          $out .= $val;
       }

      $out .= "<br /><br />";

      $this->debug_output .= $out;

    }
  }

    function array_insert_assoc($input,$input_key,$insert_key,$insert_value,$position){
    foreach ($input as $key=>$value) {
      if($key != $input_key){
  	    $temp_input[$key] = $value;
      }else{
        if($position == 'before'){
          $temp_input[$insert_key] = $insert_value;
  	      $temp_input[$key] = $value;
        }
        if($position == 'after'){
  	      $temp_input[$key] = $value;
          $temp_input[$insert_key] = $insert_value;
        }
      }
    }
    return $temp_input;
  }

  function exit_error($msg){

     $text = "<h2>Datagrid Error</h2>";

     exit($text.$msg);
  }

  function add_head_link($file,$type = 'css',$path = NULL){

    /*
    if($path == NULL){
      if($type == 'css'){
        $path = $this->paths['css_path'];
      }else if($type == 'js'){
        $path = $this->paths['js_path'];
      }
    }
    */

    if(!in_array($path.$file,$this->user_head_links[$type])){
       $this->user_head_links[$type][] = $path.$file;
    }

  }

  function truncate($string, $length, $break=" ", $pad="...") {

   // return with full string if string is shorter than $length
   if(strlen($string) <= $length) return $string;

   if($break != ''){// If break is set, only truncate if it's present

     if(false !== ($breakpoint = strpos($string, $break, $length))) {
         if($breakpoint < strlen($string) - 1) {
            $string = substr($string, 0, $breakpoint) . $pad;
         }
      }
    }else{ // Otherwise do a hard cutoff at $length
       $string = substr($string, 0, $length) . $pad;
    }
    return $string;
  }

  function footer(){
    return "<div class=\"dg_footer\"><br /></div>";
  }

  function add_button(){
    $addbutton = "
    <form name=\"add\" action=\"".$this->target_url->get()."\" method=\"get\" style=\"display: inline; padding: 0px; margin:0px\" class=\"dg_add\">
    <input type=\"hidden\" name=\"".$this->GET_pfx()."action\" value=\"add\" />";

    $addbutton .= $this->target_url->get_hidden_inputs();

    $addbutton .= "<input type=\"submit\" value=\"Add an item\" class=\"dg_add\">
    </form>";

    return $addbutton;
  }


  function currency_format($in){
    $out = '<div class="currency" style="text-align:right;';
    if($in < 0){
      $out .= ' color: red;';
      $in = abs($in);
      $prepend = '-';
    }
    $out .= '">';
    $out .= $prepend.'$'.bcadd($in,0,2);

    $out .= '</span>';
    return $out;
  }

  function add_sql_condition($str){
    $this->settings['sql_conditions'][] = $str;
  }


  private function process_grid_data(){

    $main_grid_data = $this->raw_grid_data;

    $out = array();
    $meta = array();

    if(!is_array($main_grid_data)){
      $this->dbg('Database error',$this->db->text_errors(),'error');

      $error_text = "
      SQL error in grid display query. Please check that all the table and field names used in your grid setup exist in the database.<br /><br />
      Database errors:";
      $error_text .= $this->db->text_errors();

      $this->exit_error($error_text);
    }

    foreach ($main_grid_data as $key=>$row) {

      $keyval = $row[$this->get_primary_key()];

      $out[$keyval] = array();
      $meta[$keyval] = array();

      foreach ($this->struct as $table => $tableinfo){

        if($tableinfo['type'] != 'child'){

          foreach ($tableinfo['fields'] as $field => $fieldattribs){


            if ($fieldattribs['display_type'] != 'none' && $this->check_hidden_field($fieldattribs['fid']) != 1){

              // To avoid column name conflicts, non-primary table column names are aliased to table_field
              if($tableinfo['type'] != 'primary'){
                $aliased_field = $table.'_'.$field;
              }else{
                $aliased_field = $field;
              }

              $fid = $table.$field;

              $value = $row[$aliased_field];

              $meta[$keyval][$aliased_field] = $fieldattribs;
              $meta[$keyval][$aliased_field]['fid'] = $fid;
              $meta[$keyval][$aliased_field]['raw_value'] = $value; // Raw value for ajax edit

              if($tableinfo['type'] == 'parent'){
                $meta[$keyval][$aliased_field]['relation_link_value'] = $row[$tableinfo['local_key']];
              }

              // This is necessary for ajax, but redundent with logic in edit(). Better solution needed...
              if(substr($fieldattribs['type'],0,12) == 'staticselect'){
                $options_ar_1 = explode(',',substr($fieldattribs['type'],13));
                $ss_options = array();
                foreach($options_ar_1 as $okey => $ovalue){
                  $ss_options[$ovalue] = $ovalue;
                }
                $this->struct[$table]['fields'][$aliased_field]['options'] = $ss_options;
                $this->fields[$fid]['options'] = $ss_options;
              }



              // Table headers

             if(!$this->display_headers[$fid]){

               if($this->sorttable == $table && $this->sortfield == $field){
                  $header['class'] = "dataheadersort";
                  $new_sort = $this->toggle_sorttype($this->sorttype);
                }else{
                  $header['class'] = "dataheader";
                  $new_sort = $this->sorttype;
                }


                if($fieldattribs['type'] != 'derivative'){
                  $header['sort_url'] = $this->target_url->get($this->GET_pfx()."sorttable=$table&".$this->GET_pfx()."sortfield=$field&".$this->GET_pfx()."sorttype=$new_sort");
                }

                $header['hide_url'] = $this->target_url->get($this->GET_pfx()."hide_field=".$fieldattribs['fid']);

                $header['title'] = $fieldattribs['title'];


                $this->display_headers[$fid] = $header;
              }


              if($fieldattribs['truncate']){
                 $trunc_break = '';
                 $trunc_pad = '...';
                 $value = $this->truncate($value,$fieldattribs['truncate'],$trunc_break,$trunc_pad);
              }


              // CALLBACK FIELDS (Display output of callback function)
              if($fieldattribs['display_callback']){
                $function = $fieldattribs['display_callback'];

                if(is_callable($function)){
                  $value = $function($this->get_primary_table(),$field,$value,$row,$keyval);
                }else{
                  $this->dbg('Error in display callback. Function doesn\'t exist',$function);
                }

              }

              // DISPLAY TEMPLATE
              else if($fieldattribs['display_template']){
                $code = $fieldattribs['display_template'];

                foreach ($row as $key=>$value) {
                	$code = str_replace('['.$key.']',$value,$code);
                }

                $value = $code;

              }else{

                if($fieldattribs['display_type'] == 'currency'){
                  $value = $this->currency_format($value);
                }
              }
              $out[$keyval][$aliased_field] = $value;
            }
          }
        }else{

          $parent_link_field = $tableinfo['parent_link_field'];
          $link_value = $row[$parent_link_field];


          $GET_pfx = $tableinfo['GET_pfx'];
          $child_title = $this->struct[$table]['title'];

          $this->display_headers[$table.'child'] = array(
          'title' => $child_title,
          'type' => 'child'
          );

          $child_url_vars = array(
            'parent_link_field' => $tableinfo['parent_link_field'],
            'child_link_field' => $tableinfo['child_link_field'],
            'link_value' => $link_value,
            'mode' => 'child',
            'display_type' => $tableinfo['display_type']
          );
          if($tableinfo['edit_url']){
            $child_url = $tableinfo['edit_url'].'?';
          }else{
            $database_name = $this->db->db_name;
            $child_url = $this->dg_relative_path.'prototype_child.php?';
            $child_url .= 'table='.$table.'&database='.$database_name.'&';
            $child_url .= "token=".$this->get_sub_token()."&dg_id=".$this->unique_dg_id."&";
          }
          $join = '';
          foreach ($child_url_vars as $key=>$value) {
          	 $child_url .= $join.$GET_pfx.$key.'='.$value;
          	 $join = '&';
          }

          if($tableinfo['display_callback']){
            $tdc_func = $tableinfo['display_callback'];

            $out[$keyval][$table.'child'] = $tdc_func($child_url_vars,$child_url);


          }else{
            if($tableinfo['show_child_link_text']){
              $show_child_link_text = $tableinfo['show_child_link_text'];
            }elseif($tableinfo['show_child_link_callback'] && is_callable($tableinfo['show_child_link_callback'])){
              $link_text_func = $tableinfo['show_child_link_callback'];
              $show_child_link_text = $link_text_func($child_url_vars,$child_url);
            }else{
              $show_child_link_text = "View/edit&nbsp;".$tableinfo['title'];
            }


            if($tableinfo['display_type'] == 'popup'){

             $out[$keyval][$table.'child'] = "
<div class=\"edit_child_cell_inactive\" onClick=\"show_child_grid_popup('$child_url')\">$show_child_link_text</div>";

            }elseif($tableinfo['display_type'] == 'iframe'){

              $out[$keyval][$table.'child'] = '
<div class="edit_child_cell_inactive" id="child_grid_link_cell_'.$table.'_'.$link_value.'">
<span id="show_child_grid_'.$table.'_'.$link_value.'" style="display:inline" onclick="javascript:show_child_grid(\''.$link_value.'\',\''.$table.'\',\''.$child_url.'\');">'.$show_child_link_text.'</span>
<span id="hide_child_grid_'.$table.'_'.$link_value.'" style="display:none"  onclick="javascript:show_child_grid(\''.$link_value.'\',\''.$table.'\');">Hide&nbsp;'.$tableinfo['title'].'</span>
</div>
';
              $meta[$keyval]['before_next_row'] .= $this->show_child_grid($table,$link_value);
            }

          }
        }
      }
    }
    $this->grid_data = $out;
    $this->dbg('Grid data',$out);
    $this->grid_meta_data = $meta;
  }


   private function grid_template(){

      $out .= "<div id =\"dg\">";

      $out .= '<table class="datagrid">
      <tr>
      <td class="add_button" rowspan="2">';

      if(strpos($this->settings['privileges'],'add') !== false){
        $out .= $this->add_button();
      }

      $out .= '</td>';

      #### Table headers ####
      // If no data is found, headers will be empty....

      if(is_array($this->display_headers)){
        foreach ($this->display_headers as $key=>$data) {
          if($data['type'] == 'child'){
             $out .= "<td class=\"childheader\" valign=\"center\">
             <span class=\"dataheader\">$data[title]</span>
             </td>";
          }else{
             $out .= "<td class=\"$data[class]\" valign=\"center\" onclick=\"javascript:location.href='$data[sort_url]'\" style=\"cursor:pointer\">
             <a class=\"$data[class]\" href=\"$data[sort_url]\">$data[title]</a>
             </td>";

           }
        }
      }

      $out .= "</tr><tr>";

      if(is_array($this->display_headers)){
        foreach ($this->display_headers as $key=>$data) {
          if($data['type'] == 'child'){
             $out .= "<td class=\"hidefield\" valign=\"top\">
             </td>";
          }else{
             $out .= "<td class=\"hidefield\">
             <a class=\"hidefield\" href=\"$data[hide_url]\">&raquo;&nbsp;hide&nbsp;&laquo;</a>
             </td>";

           }
        }
      }
      $out .= "</tr>";

      $rowcount = 0;
      foreach ($this->grid_data as $key=>$row){
        $rowcount++;
        $meta = $this->grid_meta_data[$key];

        if($rowcount % 2){
          $row_class = "odd_row";
        }else{
          $row_class = "even_row";
        }

        $out .= "<tr id=\"$key\" class=\"$row_class\" onClick=\"highlightRow('$key','$row_class')\">";
        $out .= "<td class=\"datafield row_controls\">".$this->edit_form($key)."</td>";

        foreach ($row as $field=>$value) {

          $atts = array();

          if($meta[$field]['display_style']){
            $atts['style'] = $meta[$field]['display_style'];
          }

          if($meta[$field]['display_class']){
            $atts['class'] = $meta[$field]['display_class'];
          }

          if($meta[$field]['display_id']){
            $atts['id'] = $meta[$field]['display_id'];
          }else{
            $atts['id'] = $meta[$field]['fid'].$key;
          }

          /* Ajax, added 2016-12-06 */
          if($meta[$field]['enable_ajax']){
            if($meta[$field]['relation'] == 'parent_secondary'){
              $local_key = $meta[$field]['relation_key_field'];
              $fid = $this->primary_table.$local_key;
              $data_value = $meta[$field]['relation_link_value'];
            }else{
              $fid = $meta[$field]['fid'];
              $data_value = $meta[$field]['raw_value'];
            }

            $atts['onDblClick'] = "ajaxEdit(this,'$fid','$key')";
            $atts['data-id'] = $key;
            $atts['data-fid'] = $fid;
            $atts['data-value'] = htmlspecialchars($data_value);

            $atts['class'] .= " ajax-edit";
          }

          $ats_str = '';
          foreach($atts as $at => $val){
            $ats_str .= $at.'="'.$val.'" ';
          }

          $out .= '<td class="datafield">';
          $out .= "<div $ats_str>\n";
          $out .= $value;
          $out .= '</div>';
          $out .= '</td>';

        }
        $out .= "</tr>";

        if($meta['before_next_row']){
          $out .= $meta['before_next_row'];
        }

     }

     $out .= "</table>";

     return $out;

  }

  function add_report($title,$code,$attribs = array()){

    $grid_link = $this->target_url->get(array($this->GET_pfx().'action'=>NULL, $this->GET_pfx().'report'=>NULL));

    $defaults = array(
      'beginning' => '<h2>'.$title.'</h2><p><a href="'.$grid_link.'">Return to '.$this->grid_title.' table</a></p>',
      'end' => '<p><a href="'.$grid_link.'">Return to '.$this->grid_title.' table</a></p>'
    );

    foreach ($defaults as $key=>$value) {
      if(!$attribs[$key]){
         $attribs[$key] = $value;
      }
    }

    if(!$attribs['slug']){
      $slug = strtolower(str_replace(' ','_',$title));
    }
    $this->reports[$slug] = $attribs;
    $this->reports[$slug]['code'] = $code;
    $this->reports[$slug]['title'] = $title;
  }

  private function report($slug){
    $rdata = $this->reports[$slug];

    $template = $rdata['code'];

    $this->sort();
    $this->search();
    $this->paging();
    $this->grid_query();
    $this->default_hidden_fields();
    //$this->process_grid_data();

    foreach ($this->raw_grid_data as $id=>$row) {

      $code = $template;

      foreach ($row as $key=>$value) {
        $code = str_replace("[$key]",$value,$code);

      }

    	$out .= $code;
    }
    return $rdata['beginning'].$out.$rdata['end'];
  }

  function reports_tab(){
    foreach ($this->reports as $slug=>$data) {
       $report_links[] = '<a href="'.$this->target_url->get(array($this->GET_pfx()."action"=>'report',$this->GET_pfx()."report"=>$slug)).'">'.$data['title'].'</a>';
    }
    return join($report_links,' | ');
  }


  private function paging_results_and_links(){

   // Grid feedback and paging links
    if($this->mode_shows('paging')){

      # no results
      if (!$this->queriedrows){
        $out .= '<div class="no_data">No data found</div>';
      }else{

        # results 'to the left' of ones shown
        if($this->start > 0){

            $prevstart = ($this->start - $this->limit);

            if ($prevstart < 0){
              $prevstart = "0";
            }

            $out .="
            <br clear=\"all\">
            <div align=\"left\" style=\"float:left\">
            <a href=\"".$this->target_url->get(array($this->GET_pfx().'limit' => $this->limit,$this->GET_pfx().'start'=> $prevstart))."\"><<< Previous ".$this->limit." items</a>
            </div>";
        }

        # results 'to the right' of ones shown
        if($this->displayedrows < $this->queriedrows && ($this->start + $this->limit) < $this->queriedrows){
          $nextstart = ($this->start + $this->limit);
          $out .= "
          <div align=\"right\" style=\"float:right\">
          <a href=\"".$this->target_url->get(array($this->GET_pfx().'limit' => $this->limit,$this->GET_pfx().'start'=> $nextstart))."\">Next ".$this->limit." items >>></a>
          </div>";
        }

        # All results shown
        if($this->displayedrows == $this->queriedrows){
          # With no searchquery
          if($this->displayedrows == $this->totalrows){
            $ifqueryfeedback = "in the table.";
          }
          # With a searchquery
          if($this->searchquery && $this->displayedrows == $this->queriedrows){
            $ifqueryfeedback = "that matched your query.";
          }
          $out .= "Showing all records $ifqueryfeedback";
        }
      }
    }
    $out .= "</div>";
    return $out;

  }

  function js_fields_object(){
    $out = "
    <script>
    gpfx = '".$this->settings['unique_GET_prefix']."';
    thisUrl = '".$this->target_url->get()."';
    struct = ".json_encode($this->struct).";
    fields = ".json_encode($this->fields).";
    </script>
    ";

    return $out;

  }

  function ajax_save(){
    if($this->GET('fid') && $this->GET('id')){
      $tf = $this->fid_to_table_and_field($this->GET('fid'));
      $value = $this->db->escape($this->GET('value'));
      $id = $this->db->escape($this->GET('id'));
      $sql = "UPDATE $tf[table] SET $tf[field] = '".$value."' WHERE ".$this->struct[$tf['table']]['primary_key_field']." = '$id'";

      $result = $this->db->query($sql);

      if($er = $this->db->is_error()){
        $result = array(
          "status" => "error",
          "error" => $er,
          "value" => $value
        );
      }else{
        $result = array(
          "status" => "success",
          "value" => $value
        );
      }

      echo json_encode($result);
      //echo $value;

    }
    exit();
  }

  function fid_to_table_and_field($fid){
    $field_array = $this->fields[$fid];
    $out = array();
    $out['table'] = $field_array['table'];
    $out['field'] = $field_array['field'];
    return $out;
  }

  function enable_ajax($fids = null){
    foreach($this->fields as $fid => $ats){
      if($ats['type'] == 'text'
        || $ats['type'] == 'textarea'
        || substr($ats['type'],0,6) == 'select'
        || substr($ats['type'],0,12) == 'staticselect' ){
        if(is_array($fids)){
          if(in_array($fid,$fids)){
            $this->set_field_attrib($ats['table'],$ats['field'],'enable_ajax',true);
          }
        }else{
          $this->set_field_attrib($ats['table'],$ats['field'],'enable_ajax',true);
        }
      }
    }
  }

}
?>
