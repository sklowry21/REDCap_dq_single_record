<?php
/*****************************************************************************************
**  REDCap is only available through a license agreement with Vanderbilt University
******************************************************************************************/
/* dq_single_record.php - copied from ../redcap_v5.5.21/DataComparisonTool/index.php 
   and modified to work with second entry in second project */
/**
 * PLUGIN NAME: dq_single_record.php
 * DESCRIPTION: This runs the missing required fields built in data quality rule for one record
 * VERSION:     1.0
 * AUTHOR:      Sue Lowry - University of Minnesota
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

include APP_PATH_DOCROOT  . 'ProjectGeneral/header.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

renderPageTitle("<img src='".APP_PATH_IMAGES."page_copy.png'> Single Record Missing Required Fields Tool");


// Instructions
print "<p>This page may be used for finding missing required fields for just one record. <br/><br/>
Select a record from the list below and hit the 'Execute' button. The missing data for the record will then be displayed below.";

//If user is in DAG, only show info from that DAG and give note of that
if ($user_rights['group_id'] != "") {
	print  "<p style='color:#800000;'>{$lang['global_02']}: {$lang['data_comp_tool_05']}</p>";
}


//Decide which pulldowns to display for user to choose Study ID
if ($user_rights['group_id'] == "") {
	$group_sql  = ""; 
} else {
	$group_sql  = "and d2.record in (" . pre_query("select record from redcap_data where project_id = $project_id and field_name = '__GROUPID__' and value = '".$user_rights['group_id']."'") . ")"; 
}
#print "<br/>group_sql: $group_sql<br/>";
$rs_ids_sql = "select distinct record from redcap_data where project_id = $project_id order by abs(record), record";
#print "<br/>rs_ids_sql: $rs_ids_sql<br/>";
$q = db_query($rs_ids_sql);
$records_found = 0;
// Collect record names into array
$records  = array();
while ($row = db_fetch_assoc($q)) 
{
	$records_found++;
	// Add to array
	$records[$row['record']] = $row['record'];
}
// Loop through the record list and store as string for drop-down options
$id_dropdown = "";
foreach ($records as $this_record)
{
	$id_dropdown .= "<option value='$this_record'>$this_record</option>";
}

// Table to choose record (show ONLY 1 pulldown for true Double Data Entry comparison)
print "<form action=\"".PAGE_FULL."?pid=$project_id\" method=\"post\" enctype=\"multipart/form-data\" name=\"execute\" target=\"_self\">";
print "<table class='form_border'>
		<tr>
			<td class='label_header' style='padding:10px;'>
				$table_pk_label
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
			<td class='label_header' style='padding:10px;' rowspan='2'>
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

	// Execute this rule
	$my_dq->executeRule($rule_id, $this_record);

	list ($num_discrepancies, $resultsTableHtml, $resultsTableTitle) = $my_dq->displayResultsTable($rule_info, $this_record);

	print $resultsTableTitle."<br/>";
	print $resultsTableHtml."<br/>";

}


include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
