<?php
/*
 * Created on 24/08/2006 by jose.canciani (at) gmail.com
 *
 * This webpage should read the table (inserted by collect.php)
 * and generate some statistics from the spam emails received.
 * 
 */
 
include('config.inc.php');

function createAjaxResponse($id,$response) {
	header('Content-Type: text/xml');
	echo '<?xml version="1.0" encoding="ISO-8859-1"?>';
	echo '<ajax-response>';
	echo '<response type="object" id="divUpdateClass">';
    echo '<block id="'.$id.'"><html><![CDATA[ '.$response.' ]]></html></block>';
    echo '</response>';
    echo '</ajax-response>';    
}

function getTable($type,$date) {
   global $db_uri, $mydomain;
   $return = '';
   $dates = explode('/',$date);
   
   $continue = false;
   switch ($type) {
   		case 'daily':
   			if (is_numeric($dates[0]) and is_numeric($dates[1]) and is_numeric($dates[2])) { 
	   			$continue = checkdate($dates[1],$dates[2],$dates[0]);
   			} else {
   				$continue = false;
   			} 
   			break;
   		case 'monthly':
   			if (is_numeric($dates[0]) and is_numeric($dates[1])) { 
   				$continue = checkdate($dates[1],1,$dates[0]);
   			} else {
   				$continue = false;
   			} 
   			break;
   		case 'yearly': 
   			if (is_numeric($date)) { 
   				$continue = checkdate(1,1,$date);
   			} else {
   				$continue = false;
   			} 
   			break;
   }
   if (!$continue){
   	  $return .= '<span class="errortext">Date error, please correct and try again.</span>';
   	  return $return;
   };
   
   switch ($type) {
   		case 'daily':
   			$from = mktime(0,0,0,$dates[1],$dates[2],$dates[0]);
   			$to = mktime(0,0,0,$dates[1],$dates[2]+1,$dates[0]);
   			break;
   		case 'monthly':
   			$from = mktime(0,0,0,$dates[1],1,$dates[0]);
   			$to = mktime(0,0,0,$dates[1]+1,1,$dates[0]); 
   			break;
   		case 'yearly': 
   			$from = mktime(0,0,0,1,1,$date);
   			$to = mktime(0,0,0,1,1,$date+1); 
   			break;
   }
   try {
   	   include('adodb_lite/adodb.inc.php');
   	   $db = NewADOConnection($db_uri);
   	   $sql = 	' select recv_email, count(1) as recv_total '.
	   			' from spam_occurance '.
	   			' where ts between ? and ? '.
	   			' group by recv_email '.
	   			' order by recv_total desc ';
	   $rs = $db->SelectLimit($sql,50,-1,array($from,$to));
   } catch (exception $e) {
       $return .= '<span class="errortext">Exception detected.</span>';
   }
   
   $return .= '<div class="closediv"><img src="images/close.jpg" onClick="javascript:closeReportDiv(\''.$type.'\');" alt="Close" /></div>';
   $return .='<table>';
   $return .= '<tr><th colspan="3">Top Spam Recipients</th></tr>';
   if ($rs === false) {
   	   $return .= '<tr><td colspan="3">Error in the db query: '.$db->ErrorMsg().'</td></tr>';
   } elseif ($rs->EOF) {
   	   $return .= '<tr><td colspan="3">No data found.</td></tr>';
   } else {
   	   $return .= '<tr><th>User</th><th>at</th><th>Count</th></tr>';
	   foreach ($rs as $row) {
	   	  $email = explode('@',$row['recv_email']);
	  	  $return .= '<tr><td>'.$email[0].'</td><td>'.$email[1].'</td><td class="number">'.$row['recv_total'].'</td></tr>';
	   }
   }
   $return .= "</table>";
   
   try {
   	   $sql = 	' select recv_email, count(1) as recv_total '.
	   			' from spam_occurance '.
	   			' where ts between ? and ? '.
	   			' and recv_email like '."'%$mydomain'".' '.
	   			' group by recv_email '.
	   			' order by recv_total desc ';
	   $rs = $db->SelectLimit($sql,50,-1,array($from,$to));
   } catch (exception $e) {
       $return .= '<span class="errortext">Exception detected.</span>';
   }
   
   if ($mydomain) {
	   $return .='<table>';
	   $return .= '<tr><th colspan="3">Top Spam Originators<br/>(filter: '.$mydomain.')</th></tr>';
	   if ($rs === false) {
	   	   $return .= '<tr><td colspan="2">Error in the db query: '.$db->ErrorMsg().'</td></tr>';
	   } elseif ($rs->EOF) {
	   	   $return .= '<tr><td colspan="2">No data found.</td></tr>';
	   } else {
	   	   $return .= '<tr><th>Site Name</th><th>Count</th></tr>';
		   foreach ($rs as $row) {
		   	  $email = explode('@',$row['recv_email']);
		  	  $return .= '<tr><td>'.$email[0].'</td><td class="number">'.$row['recv_total'].'</td></tr>';
		   }
	   }
	   $return .= "</table>";
   }
   
   switch ($type) {
   		case 'daily':
   			break;
   		case 'monthly':
   			//include the flash file, the charts library and the php file that has the chart details
   			$return .= myInsertChart('Daily Spam Statistic',"reports.php?type=xmlday&from=$from&to=$to");
   			break;
   		case 'yearly':
   			//include the flash file, the charts library and the php file that has the chart details
   			$return .= myInsertChart('Daily Spam Statistic',"reports.php?type=xmlday&from=$from&to=$to");
   			$return .= myInsertChart('Monthly Spam Statistic',"reports.php?type=xmlmonth&from=$from&to=$to");
   			break;
   }
   
   return $return;    			
}

function myInsertChart($title = '',$p3,$p4='290',$p5='200',$p6='FFFFFF',$p1='charts/charts.swf',$p2="charts/charts_library") {
	//include charts.php to access the InsertChart function
    include_once('charts/charts.php');
	return '<h3>'.$title.'</h3><div class="graph">'.InsertChart($p1,$p2,$p3,$p4,$p5,$p6).'</div>';
}						

function createXmlData($type,$from,$to) {
	
	global $db_uri;
	
	include "charts/charts.php";
	//setting the chart's default attributes
	$chart = array();
	$chart['chart_data'] = array(array(""));
	$chart['chart_type'] = array ('type'=>'column');
	if (!is_numeric($from) or !is_numeric($to)) {
		$chart['draw'][] = array ( 'type'=>"text", 'color'=>'FF0000', 'size'=> '12', 'x'=>95, 'y'=>90, 'text'=>"Error in parameters" );
	} elseif (!in_array($type,array('xmlday','xmlmonth'))) {
		$chart['draw'][] = array ( 'type'=>"text", 'color'=>'FF0000', 'size'=> '12', 'x'=>95, 'y'=>90, 'text'=>"Error in graph type" );
	} else {
		try {
	   	   	include('adodb_lite/adodb.inc.php');
	   	   	$db = NewADOConnection($db_uri);
	   	   	$sql = '';
	   	   	$array = array();
	   	   	switch ($type) {
	   	   		case 'xmlday':
			   	   $sql = 	' select dayname(FROM_UNIXTIME(ts)) as thename, count(1) as recv_total '.
				   			' from spam_occurance '.
				   			' where ts between ? and ? '.
				   			' group by thename';
				   $array = array(	'Sunday'=>0,
									'Monday'=>0,
									'Tuesday'=>0,
									'Wednesday'=>0,
									'Thursday'=>0,
									'Friday'=>0,
									'Saturday'=>0);
					break;
				case 'xmlmonth':
					$sql = 	' select monthname(FROM_UNIXTIME(ts)) as thename, count(1) as recv_total '.
				   			' from spam_occurance '.
				   			' where ts between ? and ? '.
				   			' group by thename';
				    $array = array(	'January'=>0,
									'February'=>0,
									'March'=>0,
									'April'=>0,
									'May'=>0,
									'June'=>0,
									'July'=>0,
									'August'=>0,
									'September'=>0,
									'October'=>0,
									'November'=>0,
									'December'=>0);
					break;
	   	   	}
			$rs = $db->Execute($sql,array($from,$to));
			if ($rs === false) {
		   	   $chart['draw'][] = array ( 'type'=>"text", 'color'=>'FF0000', 'size'=> '12', 'x'=>95, 'y'=>90, 'text'=>"Error in query" );
		   	} elseif ($rs->EOF) {
		   	   $chart['draw'][] = array ( 'type'=>"text", 'color'=>'FF0000', 'size'=> '12', 'x'=>95, 'y'=>90, 'text'=>"No data found" );
		   	} else {
		    	foreach ($rs as $row) {
		    		$array[$row['thename']] = $row['recv_total'];
		    	}
		    	switch ($type) {
	   	   			case 'xmlday':
		    			$chart['chart_data'] = array (
		    					array("Days",'Sun','Mon','Tue','Wed','Thu','Fri','Sat'),
								array("Msg Count",$array['Sunday'],$array['Monday'],$array['Tuesday'],
									  $array['Wednesday'],$array['Thursday'],
									  $array['Friday'],$array['Saturday'])
		    		 			);
		    		 	break;
					case 'xmlmonth':
		    			$chart['chart_data'] = array (
		    					array("Months","Jan",'Feb','Mar','Apr','May',
									 'Jun','Aug','Sep','Oct','Nov','Dec'),
								array("Msg Count", $array['January'],$array['February'],$array['March'],
									  $array['April'],$array['May'],
									  $array['June'],$array['July'],
									  $array['August'],$array['September'],$array['October'],
									  $array['November'],$array['December'])
		    		 			);
		    		 	$chart['chart_type'] = array ('type'=>'line');
		    		 	break;
		    	}		    		 
			}
	    } catch (exception $e) {
	    	$chart['chart_data'] = array(array(""));
	       	$chart['draw'][] = array ( 'type'=>"text", 'color'=>'FF0000', 'size'=> '12', 'x'=>95, 'y'=>90, 'text'=>"Error in Database" );
	    }    
	}
	SendChartData($chart);
	exit;
}

if (isset($_REQUEST['type']) and in_array($_REQUEST['type'],array('daily','monthly','yearly'))) {
	createAjaxResponse($_REQUEST['type'],getTable($_REQUEST['type'],$_REQUEST['date']));
	exit;		
}

if (isset($_REQUEST['type']) and in_array($_REQUEST['type'],array('xmlday','xmlmonth'))) {
	createXmlData($_REQUEST['type'],$_REQUEST['from'],$_REQUEST['to']);
	exit;		
}


// lets create the container for the reports
?>
<html>
  <head>
    <script type="text/javascript" src="js/rico2/prototype.js"></script>
    <script type="text/javascript" src="js/rico2/rico.js"></script>
    <script type="text/javascript" src="js/rico2/ricoAjax.js"></script>
    <link rel="stylesheet" type="text/css" href="styles/reports.css" />
    
    <script language="JavaScript">
        // divUpdateClass
		var divUpdateClass = Class.create();
		divUpdateClass.prototype = {
			initialize: function() {      
			},
			ajaxUpdate: function(ajaxResponse) {
				for (var i=0; i < ajaxResponse.childNodes.length; i++) {
					this.updateDiv(ajaxResponse.childNodes[i]);
				};
			},
			updateDiv: function(aBlock) {
				// set the div with map data
				var id = aBlock.getAttribute('id');
				var block_html_element = aBlock.childNodes[0];
			
				// change innerHTML for the block
				var block = document.getElementById(id);
				if (block) {
					block.innerHTML = RicoUtil.getContentAsString(block_html_element);
				}
			}
		 }
		 
		 function runDivUpdate(the_type,the_date) {
		 	block = document.getElementById(the_type);
		 	block.innerHTML = 'Loading ...';
		 	var params = 'type='+the_type+'&date='+the_date;
		 	var options = {onFailure: reportError, parameters: params};
		 	ajaxEngine.sendRequest('divUpdateRequest',options);
         }
         
         function reportError() {
         	alert('Ooops, there was an error. Try again later.');
         }         
		 
		 ajaxEngine.registerRequest( 'divUpdateRequest', 'reports.php' );
	     ajaxEngine.registerAjaxObject( 'divUpdateClass', new divUpdateClass() );
	     
	     function closeReportDiv(id) {
	     	block = document.getElementById(id);
		 	block.innerHTML = 'Click "Update" to view the report.';
	     }
	</script>    
  </head>
  <body>
    <h1>Spam analysis for <?  
      $email = explode('@',$myemail);
      echo $email[0].' at '.$email[1];
    ?></h1>
    <div id="formcontainer" class="clearfix">
    	<div id="daily-form">
    	   Daily Report<br/>
	       <input id="dformtext" type="text" value="<?=date('Y/m/d')?>" class="textinput">
	       <input type="submit" value="Update" class="submitinput" onClick="runDivUpdate('daily',document.getElementById('dformtext').value);">
	    </div>
	    <div id="monthly-form">
	       Monthly Report<br/>
	       <input id="mformtext" type="text" value="<?=date('Y/m')?>" class="textinput">
	       <input type="submit" value="Update" class="submitinput" onClick="runDivUpdate('monthly',document.getElementById('mformtext').value);">
	    </div>
	    <div id="yearly-form">
	       Yearly Report<br/>
	       <input id="yformtext" type="text" value="<?=date('Y')?>" class="textinput">
	       <input type="submit" value="Update" class="submitinput" onClick="runDivUpdate('yearly',document.getElementById('yformtext').value);">
	    </div>
	</div>
	<div id="reportcontainer" class="clearfix">
	    <div id="daily" style="display: inline;">Click "Update" to view the report.</div>
	    <div id="monthly" style="display: inline;">Click "Update" to view the report.</div>
	    <div id="yearly" style="display: inline;">Click "Update" to view the report.</div>
	</div>
  </body>
</html>