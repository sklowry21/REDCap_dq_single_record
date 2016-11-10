<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/
/* dq_single_record.php - copied from ../redcap_v5.5.21/DataComparisonTool/index.php 
   and modified to work with second entry in second project */
/**
 * PLUGIN NAME: dq_single_record.php
 * DESCRIPTION: This runs the missing required fields built in data quality rule for one record 
 *              or a limited number of records
 * VERSION:     1.1
 * AUTHOR:      Sue Lowry - University of Minnesota
 * 1.1 - Changed to allow checking for a limited number of records in addition to one record
 */

// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";
#require_once APP_PATH_DOCROOT . 'Reports/functions.php';

require_once "DataQualityByRecord.php";

if (!isset($_GET['pid'])) {
    exit("Project ID is required");
}
$pid = $_GET['pid'];

#print "pid: $pid</br>";
if (!SUPER_USER) {
    $sql = sprintf( "
            SELECT p.app_title
              FROM redcap_projects p
              LEFT JOIN redcap_user_rights u
                ON u.project_id = p.project_id
             WHERE p.project_id = %d AND (u.username = '%s' OR p.auth_meth = 'none')",
                     $_REQUEST['pid'], $userid);

    // execute the sql statement
    $result = $conn->query( $sql );
    if ( ! $result )  // sql failed
    {
        die( "Could not execute SQL: <pre>$sql</pre> <br />" .  mysqli_error($conn) );
    }

    if ( mysqli_num_rows($result) == 0 )
    {
        die( "You are not validated for project # $project_id ($app_title)<br />" );
    }
}
$check_some_label = "Execute for limited number of records";
$skip_recs = 0;
if (isset($_POST['skip_recs']) ) { $skip_recs = $_POST['skip_recs']; }
$first_rec_num = $skip_recs + 1;
$rec_limit = 10;
if (isset($_POST['rec_limit']) ) { $rec_limit = $_POST['rec_limit']; }
$check_some = 0;
if (isset($_POST['submit']) and $_POST['submit'] == $check_some_label) { $check_some = 1; }
if ($check_some == 1 and ($skip_recs > 0 or $rec_limit > 0)) {
        $limit_sql = "limit " . $skip_recs . ", $rec_limit";
} else {
        $limit_sql = "";
}

include APP_PATH_DOCROOT  . 'ProjectGeneral/header.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

renderPageTitle("<img src='".APP_PATH_IMAGES."page_copy.png'> Single Record Missing Required Fields Tool");


// Instructions
print "<p>This page may be used for finding missing required fields for just one record or for a limited number of records at a time. <br/><br/>
Select a record from the list below and click on the 'Execute' button - OR - enter a number of records to be checked and click on the 'Execute for linmited number of records' button. The missing data for the record(s) will then be displayed below.";

//If user is in DAG, only show info from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['data_comp_tool_05']}</p>";
}

// Set flag to check ALL records instead of single record
$checkAll = (isset($_POST['check-all']) && $_POST['check-all']);
if (isset($_GET['rec_limit']) ) {
   $checkAll = (isset($_GET['rec_limit']) );
}


//Decide which pulldowns to display for user to choose Study ID
if ($user_rights['group_id'] == "") {
	$group_sql  = ""; 
} else {
	$group_sql  = "and record in (" . pre_query("select record from redcap_data where project_id = $project_id and field_name = '__GROUPID__' and value = '".$user_rights['group_id']."'") . ")"; 
}
#print "<br/>group_sql: $group_sql<br/>";
$rs_ids_sql = "select distinct record from redcap_data where project_id = $project_id and field_name = '$table_pk' $group_sql order by abs(record), record $limit_sql";
#print "<br/><br/>rs_ids_sql: $rs_ids_sql<br/>";
$q = db_query($rs_ids_sql);
$records_found = 0;
// Collect record names into array
$records  = array();
while ($row = db_fetch_assoc($q)) 
{
	$records_found++;
	// Add to array
	$records[$records_found] = $row['record'];
}
// Loop through the record list and store as string for drop-down options
$id_dropdown = "";
foreach ($records as $this_record)
{
	$id_dropdown .= "<option value='$this_record'>$this_record</option>";
}
#print_r($records);

// Give option to check all DDE pairs of records on single page
$checkAllBtn = '';
$disableCompAllBtn = (empty($records)) ? "disabled" : "";
$checkAllBtn = RCView::div(array('style'=>'padding:5px 0;font-weight:normal;color:#777;'),
                                        "&mdash; {$lang['global_46']} &mdash;"
                                 ) .
                                 RCView::div('',
                                        RCView::input(array('type'=>'submit','name'=>'submit','value'=>$lang['data_comp_tool_45'],$disableCompAllBtn=>$disableCompAllBtn,'onclick'=>"$('#record1').val($('#record1 option:eq(1)').val()); $('input[name=\"check-all\"]').val('1');"))
                                 );
if ($check_some == 1) {
        $new_skip = $skip_recs + $rec_limit;
        if (empty($records)) {
                $new_skip = 0;
                $disableCompAllBtn = "";
        }
} else {
        $new_skip = $skip_recs;
}
$checkSomeBtn = RCView::div(array('style'=>'padding:5px 0;font-weight:normal;color:#777;'),
                                        "&mdash; {$lang['global_46']} &mdash;"
                                 ) .
                                 "Check up to <input name='rec_limit' value=$rec_limit size=6 class='x-form-text x-form-field' style-'padding-right:0;height:22px;'> records,<br>" .
                                 "skipping the first <input name='skip_recs' value=$new_skip size=6 class='x-form-text x-form-field' style-'padding-right:0;height:22px;'> records" .
                                 RCView::div('',
                                        RCView::input(array('type'=>'submit','name'=>'submit','value'=>$check_some_label,$disableCompAllBtn=>$disableCompAllBtn,'onclick'=>"$('#record1').val($('#record1 option:eq(1)').val()); $('input[name=\"check-some\"]').val('5-10');"))
                                 );



// Table to choose record
print "<form action=\"".PAGE_FULL."?pid=$project_id\" method=\"post\" enctype=\"multipart/form-data\" name=\"execute\" target=\"_self\">";
print "<table class='form_border'>
		<tr>
			<td class='label_header' style='padding:10px;'>
				$table_pk_label
			</td>
                        <td class='label_header' style='padding:10px;' rowspan='3'>
                                $checkSomeBtn
                        </td>
 		</tr>
		<tr>
			<td class='data' align='center' style='padding:15px;'>
				<select name='record' id='record' class='x-form-text x-form-field' style='padding-right:0;height:22px;'>
					<option value=''>--- {$lang['data_comp_tool_43']} ---</option>
					$id_dropdown
				</select>
			</td>
		</tr>
		<tr>
			<td class='label_header' style='padding:10px;'>
				<input name='submit' type='submit' value='Execute'>
			</td>
		</tr>
	</table>
</form><br><br>";	

// If record passed in, use javascript to select the dropdown values
if ( $_GET['record'] )
{
	// pre-select the drop-down(s)
	print  "<script type='text/javascript'>
			$(function(){
				$('#record').val('{$_GET['record']}');
			});
		</script>";
}


// If sumbitted values, use javascript to select the dropdown values
if ($_SERVER['REQUEST_METHOD'] == 'POST') 
{
	// pre-select the drop-down(s)
	print  "<script type='text/javascript'>
			$(function(){
				$('#record').val('{$_POST['record']}');
			});
		</script>";
}


###############################################################################################
# When records are selected for checking, execute the data quality rule
if (isset($_POST['submit'])) 
{
	$this_record = $_POST['record'];
	$rule_id = 'pd-6';
	$my_dq =  new DataQualityByRecord();
	// Get rule info
	$rule_info = $my_dq->getRule($rule_id);

        if ($check_some == 1)
        {
                print "<h3><u>Checking $records_found records starting with record # $first_rec_num:</u></h3>";
        }

        // If only comparing a single record
        if (!$checkAll and !$check_some) {
            $records = array($this_record);
        }

        // Loop through records
        foreach ($records as $this_record)
        {
 	    // Execute this rule
	    $my_dq->executeRule($rule_id, $this_record);

	    list ($num_discrepancies, $resultsTableHtml, $resultsTableTitle) = $my_dq->displayResultsTable($rule_info, $this_record);

	    print "<h4>$table_pk_label $this_record</h4>".$resultsTableTitle."<br/>";
	    print $resultsTableHtml."<br/>";

	}
}


include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
