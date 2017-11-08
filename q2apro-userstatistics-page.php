<?php
/*
	Plugin Name: q2apro User Statistics
	Plugin Author: q2apro.com
*/

	class q2apro_userstatistics
	{

		var $directory;
		var $urltoroot;

		function load_module($directory, $urltoroot)
		{
			$this->directory=$directory;
			$this->urltoroot=$urltoroot;
		}

		// for display in admin interface under admin/pages
		function suggest_requests()
		{
			return array(
				array(
					'title' => 'User Statistics Page', // title of page
					'request' => 'userstats', // request name
					'nav' => 'M', // 'M'=main, 'F'=footer, 'B'=before main, 'O'=opposite main, null=none
				),
			);
		}

		// for url query
		function match_request($request)
		{
			if($request=='userstats')
			{
				return true;
			}

			return false;
		}

		function process_request($request)
		{

			/* start content */
			$qa_content = qa_content_prepare();

			// page title
			$qa_content['title'] = qa_lang('q2apro_userstatistics_lang/page_title');

			// init
			$qa_content['custom'] = '';

			$userhandle = qa_get('username'); // userhandle in URL
			$userid = '';
			if(isset($userhandle))
			{
				// get userid from handle
				$userid = qa_db_read_one_value(
							qa_db_query_sub('SELECT userid FROM ^users
												WHERE handle = #
												', $userhandle),
											true);
			}
			else
			{
				$userid = qa_get_logged_in_userid();
				// get handle from userid
				$userhandle = qa_db_read_one_value(
								qa_db_query_sub('SELECT handle FROM ^users
													WHERE handle = #
												', $userid),
											true);
			}

			// user avatar and main data
			$userdata_p = qa_db_read_one_assoc(
								qa_db_query_sub('SELECT points, upvoteds, qupvotes, aupvotes, aselecteds FROM ^userpoints
													WHERE userid = #',
													$userid), true
											);

			$userdata = qa_db_read_one_assoc(
								qa_db_query_sub('SELECT created, avatarblobid FROM ^users
													WHERE userid = #',
												$userid), true);

			$avatarblobid = $userdata['avatarblobid'];
			if(is_null($avatarblobid))
			{
				$avatarblobid = qa_opt('avatar_default_blobid');
			}
			$bestanswer_title = qa_lang('q2apro_userstatistics_lang/bestanswers');
			$thumbsup_title = qa_lang('q2apro_userstatistics_lang/votes_received');
			$points_label = qa_lang('q2apro_userstatistics_lang/totalpoints');
			$aselecteds = $userdata_p['aselecteds'];
			$points = $userdata_p['points'];
			$upvoteds = $userdata_p['upvoteds'];
			$qupvotes = $userdata_p['qupvotes'];
			$aupvotes = $userdata_p['aupvotes'];
			$membertime = qa_time_to_string(qa_opt('db_time') - strtotime($userdata['created']));

			$qa_content['custom'] .= '
				<div class="profileimage">

					<img src="'.qa_path('').'?qa=image&qa_blobid='.$avatarblobid.'&qa_size=250" alt="'.$userhandle.'" class="qa-avatar-image" />

					<div class="reputationlab">
						<span class="upro_bestanswers tooltipS" title="'.$bestanswer_title.'">
							<img src="'.$this->urltoroot.'images/best-answer.png" alt="Best answer" style="margin-right:10px;" /><br />
							<span class="upro_bestanswers_count">'.$aselecteds.'</span>
						</span>

						<span class="upro_points">'.number_format($points, 0, ',', '.').'</span> <br />

						<span class="upro_pointslabel">'.$points_label.'</span>

						<span class="upro_upvoteds tooltipS" title="'.$upvoteds.' '.$thumbsup_title.'<br />'.($qupvotes+$aupvotes).' '.qa_lang('q2apro_userstatistics_lang/votes_given').'">
							<img src="'.$this->urltoroot.'images/thumbs-up.png" alt="Thumbs up" />
							<span class="upro_upvoteds_count">'.$upvoteds.'</span>
						</span>
					</div>
					<p>
						'.qa_lang('q2apro_userstatistics_lang/membersince').' '.$membertime.'
					</p>
				</div> <!-- profileimage -->
			';

			// time range - this month as default
			$chosenMonth = date('Y-m-01');

			// we received post data, user has chosen a month
			if(qa_post_text('request'))
			{
				$chosenMonth = qa_post_text('request');
				// sanitize string, keep only 0-9 and -
				$chosenMonth = preg_replace("/[^0-9\-]/i", '', $chosenMonth);
			}
			// get interval start from chosen month
			$startdate = date("Y-m-01", strtotime($chosenMonth) ); // 05/2015 becomes 2015-05-01
			$enddate = date("Y-m-t", strtotime($chosenMonth)); // get last day of month

			// read month names from admin option
			$monthnames_string = qa_opt('q2apro_userstatistics_months');
			$monthnames = explode(',', $monthnames_string);

			// title with month date
			$qa_content['title'] = qa_lang('q2apro_userstatistics_lang/page_title').' - '.$monthnames[(int)(substr($chosenMonth,5,2))-1].' '.substr($chosenMonth,0,4);

			// get all questions
			$queryQuestions = qa_db_query_sub('SELECT created
													FROM `^posts`
													WHERE `type`="Q"
														AND DATE(`created`) BETWEEN DATE(#) AND DATE(#)
														AND `userid` = #
													ORDER BY created DESC',
														$startdate, $enddate, $userid
													);
			// get all answers
			$queryAnswers = qa_db_query_sub('SELECT created
													FROM `^posts`
													WHERE `type`="A"
														AND DATE(`created`) BETWEEN DATE(#) AND DATE(#)
														AND `userid` = #
													ORDER BY created DESC',
														$startdate, $enddate, $userid
													);
			// get all best answers
			$queryBestAnswers = qa_db_query_sub('SELECT a.created
													FROM `^posts` q
													JOIN `^posts` a ON q.selchildid = a.postid
													WHERE a.userid = #
														AND DATE(a.created) BETWEEN DATE(#) AND DATE(#)
													ORDER BY created DESC',
														$userid, $startdate, $enddate
													);

			// get all comments
			$queryComments = qa_db_query_sub('SELECT created
													FROM `^posts`
													WHERE `type`="C"
														AND DATE(`created`) BETWEEN DATE(#) AND DATE(#)
														AND `userid` = #
													ORDER BY created DESC',
														$startdate, $enddate, $userid
													);


			// prepare questions count
			$questions = array();
			$weekAskTime = array(0,1,2,3,4,5,6);
			$jsData_forTableQ = '';
			$jsData_forTableQ2 = '';
			$jsData_forTableA = '';
			$jsData_forTableC = '';
			$q = 0;
			while( ($row = qa_db_read_one_assoc($queryQuestions,true)) !== null )
			{
				// take substring, i. e. only date from "2013-01-04 15:18:03"
				$questions[++$q] = substr($row['created'],0,10);
				$weekday = date( 'w', strtotime($row['created']) ); // w, N, D
				// write time 12:12 to array, according to weekday number (0-6) (Sun-Sat)
				$weekAskTime[$weekday] .= substr($row['created'],11,5).',';
				// data for jquery flot plotting
				$jsData_forTableQ2 .= '['.($weekday+1).', '.$this->convertTimeToSec(substr($row['created'],11,7)).'], ';
			}

			// prepare answers count
			$answers = array();
			$a = 0;
			while( ($row = qa_db_read_one_assoc($queryAnswers,true)) !== null )
			{
				$answers[++$a] = substr($row['created'],0,10);
			}

			// prepare best answers count
			$bestanswers = array();
			$a = 0;
			while( ($row = qa_db_read_one_assoc($queryBestAnswers,true)) !== null )
			{
				$bestanswers[++$a] = substr($row['created'],0,10);
			}

			// prepare comments count
			$comments = array();
			$c = 0;
			while( ($row = qa_db_read_one_assoc($queryComments,true)) !== null )
			{
				$comments[++$c] = substr($row['created'],0,10);
			}

			// sort array by values
			$questionsDays = array_count_values($questions); // keys are the original array's values and the values are the number of occurrences
			$answersDays = array_count_values($answers);
			$bestanswersDays = array_count_values($bestanswers);
			$commentsDays = array_count_values($comments);

			$sumQ = 0;
			$sumA = 0;
			$sumBA = 0;
			$sumC = 0;
			$countDays = 0;
			$tableHTML = '';

			$day = $startdate;

			$tableHTML .= '
			<h3>
				'.qa_lang('q2apro_userstatistics_lang/dailystats').'
			</h3>
			<table class="tablesorter">
				<thead>
					<tr>
						<th>'.qa_lang('q2apro_userstatistics_lang/date').'</th>
						<th>'.qa_lang('q2apro_userstatistics_lang/questions').'</th>
						<th>'.qa_lang('q2apro_userstatistics_lang/answers').'</th>
						<th>'.qa_lang('q2apro_userstatistics_lang/bestanswers').'</th>
						<th>'.qa_lang('q2apro_userstatistics_lang/bestquota').'</th>
						<th>'.qa_lang('q2apro_userstatistics_lang/comments').'</th>
					</tr>
				</thead>
			';

			$weekdaynames_string = qa_opt('q2apro_userstatistics_weekdays');
			$weekdaynames = explode(',', $weekdaynames_string);

			// go over all days
			while(strtotime($day) <= strtotime($enddate))
			{
				$daynumber = (int)date("N", strtotime($day))-1;
				$weekdayname = $weekdaynames[$daynumber];
				// get questions
				$questionsThatDay = (isset($questionsDays[$day]) && $questionsDays[$day]>0) ? $questionsDays[$day] : 0;
				// get answers
				$answersThatDay = (isset($answersDays[$day]) && $answersDays[$day]>0) ? $answersDays[$day] : 0;
				// get best answers
				$bestanswersThatDay = (isset($bestanswersDays[$day]) && $bestanswersDays[$day]>0) ? $bestanswersDays[$day] : 0;
				// get comments
				$commentsThatDay = (isset($commentsDays[$day]) && $commentsDays[$day]>0) ? $commentsDays[$day] : 0;

				// do not add row if all 0
				if($questionsThatDay+$answersThatDay+$bestanswersThatDay+$commentsThatDay > 0)
				{
					$answthatday = $answersThatDay>0 ? round(100*$bestanswersThatDay/$answersThatDay,2) : 0;
					$tableHTML .= '
					<tr>
						<td>'.date(qa_opt('q2apro_userstatistics_dateformat'), strtotime($day)).', '.$weekdayname.'</td>
						<td>'.$questionsThatDay.'</td>
						<td>'.$answersThatDay.'</td>
						<td>'.$bestanswersThatDay.'</td>
						<td>'.$answthatday.' %</td>
						<td>'.$commentsThatDay.'</td>
					</tr>
					';
				}
				$sumQ += $questionsThatDay;
				$sumA += $answersThatDay;
				$sumBA += $bestanswersThatDay;
				$sumC += $commentsThatDay;
				$countDays++;
				$dayjs = str_replace('-', '/', $day);
				$jsData_forTableQ .= '[(new Date("'.$dayjs.'")).getTime(), '.$questionsThatDay.'], ';
				$jsData_forTableA .= '[(new Date("'.$dayjs.'")).getTime(), '.$answersThatDay.'], ';
				$jsData_forTableC .= '[(new Date("'.$dayjs.'")).getTime(), '.$commentsThatDay.'], ';
				// iterate to next day
				$day = date ("Y-m-d", strtotime("+1 day", strtotime($day)));
			}

			// comments disabled
			// $jsData_forTableC = '[]';


			// catch wrong date, e.g. 2013-05-01 to 2013-04-01
			if($countDays==0)
			{
				$qa_content['error'] ='
						<p>
							'.qa_lang('q2apro_userstatistics_lang/wrongdate').': '.$startdate.' - '.$enddate.'
						</p>
					';
				return $qa_content;
			}

			$imgCount = 1;
			$imgDelCount = 1;

			$qa_content['custom'] .= '
				<link rel="stylesheet" type="text/css" href="'.$this->urltoroot.'/zebra_datepicker/default.css">
				<script type="text/javascript" src="'.$this->urltoroot.'/zebra_datepicker/zebra_datepicker.js"></script>
				<script src="'.$this->urltoroot.'jquery.tablesorter.min.js"></script>
				<script type="text/javascript" src="'.$this->urltoroot.'graphTable.flot.min.js"></script>
			';

			$lastmonthstart = date("Y-m-d", mktime(0, 0, 0, date("m")-1, 1, date("Y")));
			$lastmonthend = date("Y-m-d", mktime(0, 0, 0, date("m"), 0, date("Y")));

			// inputs for date range
			/*
			$listStatistics .= '
			<form class="ludatr">
				<label for="startdate">Von: </label>
					<input type="text" name="startdate" value="'.$startdate.'" />
				<label for="enddate">bis: </label>
					<input type="text" name="enddate" value="'.$enddate.'" />
				<input type="submit" value="Anzeigen" class="btnblue" style="margin-left:10px;" />
			</form>
			';
			*/

			// init
			$listStatistics = '';

			// date input field
			$listStatistics .= '
			<form method="post" action="" id="datepick">
				<span style="font-size:14px;">'.qa_lang('q2apro_userstatistics_lang/choosemonth').': &nbsp;</span>
				<input value="'.substr($chosenMonth,0,7).'" id="datepicker" name="request" type="text">
			</form>
			';

			$sumposts = $sumQ+$sumA+$sumC;

			$sumQ_p = $sumQ*(int)qa_opt('points_post_q');
			$sumA_p = $sumA*(int)qa_opt('points_post_a');
			$sumBA_p = $sumBA*(int)qa_opt('points_a_selected');

			$sumQ_voted = 0;
			$sumA_voted = 0;

			// $queryQuestionVotes - get all question that user received votes on
			$sumQ_voted = qa_db_read_one_value(
							qa_db_query_sub('SELECT SUM(upvotes)
												FROM `^posts`
												WHERE `type`="Q"
													AND DATE(`created`) BETWEEN DATE(#) AND DATE(#)
													AND `userid` = #
												ORDER BY created DESC',
													$startdate, $enddate, $userid
												), true);
			// $queryAnswerVotes - get all question that user received votes on
			$sumA_voted = qa_db_read_one_value(
							qa_db_query_sub('SELECT SUM(upvotes)
												FROM `^posts`
												WHERE `type`="A"
													AND DATE(`created`) BETWEEN DATE(#) AND DATE(#)
													AND `userid` = #
												ORDER BY created DESC',
													$startdate, $enddate, $userid
												), true);

			$sumQ_votes = $sumQ_voted*(int)qa_opt('points_per_q_voted_up');
			$sumA_votes = $sumA_voted*(int)qa_opt('points_per_a_voted_up');
			$sum_votes = $sumQ_votes+$sumA_votes;

			// $queryAnswerVotes - get all question that user received votes on
			$sumAsel = qa_db_read_one_value(
							qa_db_query_sub('SELECT COUNT(postid)
												FROM `^posts`
												WHERE `type`="Q"
													AND selchildid IS NOT NULL 
													AND DATE(`created`) BETWEEN DATE(#) AND DATE(#)
													AND `userid` = #
												ORDER BY created DESC',
													$startdate, $enddate, $userid
												), true);

			$sumAsel_p = $sumAsel*(int)qa_opt('points_select_a');

			$sumpoints = $sumQ_p+$sumA_p+$sumBA_p+$sum_votes;

			// add userstattable
			$listStatistics .= '
			<table class="userstattable">
				<thead>
					<tr>
						<th>
							&nbsp;
						</th>
						<th>
							'.qa_lang('q2apro_userstatistics_lang/total').'
						</th>
						<th>
							'.qa_lang('q2apro_userstatistics_lang/points').'
						</th>
						<!--
							<th>'.qa_lang('q2apro_userstatistics_lang/dailyaverage').'</th>
						-->
					</tr>
				</thead>
				<tr>
					<td>
						'.qa_lang('q2apro_userstatistics_lang/answers').'
						<span class="pointshower">
							'.qa_lang('q2apro_userstatistics_lang/each').' '.(int)qa_opt('points_post_a').' '.qa_lang('q2apro_userstatistics_lang/points').'
						</span>
					</td>
					<td>
						'.$sumA.'
					</td>
					<td>
						'.$sumA_p.'
					</td>
					<!-- <td>'.round($sumA/$countDays,1).'</td> -->
				</tr>
				<tr>
					<td>
						'.qa_lang('q2apro_userstatistics_lang/bestanswers').'
						<span class="pointshower">
							'.qa_lang('q2apro_userstatistics_lang/each').' '.(int)qa_opt('points_a_selected').' '.qa_lang('q2apro_userstatistics_lang/points').'
						</span>
					</td>
					<td>
						'.$sumBA.'
					</td>
					<td>
						'.$sumBA_p.'
					</td>
					<!-- <td>'.round($sumBA/$countDays,1).'</td> -->
				</tr>
				<tr>
					<td>
						'.qa_lang('q2apro_userstatistics_lang/votesforanswers').'
						<span class="pointshower">
							'.qa_lang('q2apro_userstatistics_lang/each').' '.(int)qa_opt('points_per_a_voted_up').' '.qa_lang('q2apro_userstatistics_lang/points').'
						</span>
					</td>
					<td>
						'.$sumA_votes.'
					</td>
					<td>
						'.$sumA_votes.'
					</td>
					<!-- <td>'.round($sumA_votes/$countDays,1).'</td> -->
				</tr>
				<tr>
					<td>
						'.qa_lang('q2apro_userstatistics_lang/questionsasked').'
						<span class="pointshower">
							'.qa_lang('q2apro_userstatistics_lang/each').' '.(int)qa_opt('points_post_q').' '.qa_lang('q2apro_userstatistics_lang/points').'
						</span>
					</td>
					<td>
						'.$sumQ.'
					</td>
					<td>
						'.$sumQ_p.'
					</td>
					<!-- <td>'.round($sumQ/$countDays,1).'</td> -->
				</tr>
				<tr>
					<td>
						'.qa_lang('q2apro_userstatistics_lang/bestanswers_selected').'
						<span class="pointshower">
							'.qa_lang('q2apro_userstatistics_lang/each').' '.(int)qa_opt('points_select_a').' '.qa_lang('q2apro_userstatistics_lang/points').'
						</span>
					</td>
					<td>
						'.$sumAsel.'
					</td>
					<td>
						'.$sumAsel_p.'
					</td>
					<!-- <td>'.round($sumAsel/$countDays,1).'</td>  -->
				</tr>
				<tr>
					<td>
						'.qa_lang('q2apro_userstatistics_lang/votesforquestions').'
						<span class="pointshower">
							'.qa_lang('q2apro_userstatistics_lang/each').' '.(int)qa_opt('points_per_q_voted_up').' '.qa_lang('q2apro_userstatistics_lang/points').'
						</span>
					</td>
					<td>
						'.$sumQ_votes.'
					</td>
					<td>
						'.$sumQ_votes.'
					</td>
					<!-- <td>'.round($sumQ_votes/$countDays,1).'</td> -->
				</tr>
				<tr>
					<td>
						'.qa_lang('q2apro_userstatistics_lang/comments').'
						<span class="pointshower">'.qa_lang('q2apro_userstatistics_lang/nopoints').'</span>
					</td>
					<td>
						'.$sumC.'
					</td>
					<td>
						-
					</td>
					<!-- <td>'.round($sumC/$countDays,1).'</td> -->
				</tr>
				<tr style="background:#FFF;">
					<td>
						'.qa_lang('q2apro_userstatistics_lang/totalpoints').'
					</td>
					<td>
						-
					</td>
					<td>
						'.$sumpoints.'
					</td>
					<!-- <td>-</td> -->
				</tr>
			</table> <!-- userstattable -->

			';

			$listStatistics .= $tableHTML;
			$listStatistics .= '</table> <!-- tablesorter -->';

			$listStatistics .= '
			<h3>
				'.qa_lang('q2apro_userstatistics_lang/diagram').'
			</h3>
			';
			$listStatistics .= '<div id="placeholder" style="width:100%;height:400px;">.</div>';

			$qa_content['custom'] .= $listStatistics;

			/*
			if(!isset($userhandle))
			{
				$qa_content['custom'] .= '
					<h2 style="margin-top:40px;">
						Fragendichte
					</h2>
					<div id="placeholder2" style="width:780px;height:780px;margin-top:30px;">.</div>
				';
			}
			*/

			// get user register date and set it as start date for datepicker
			$dp_start = qa_db_read_one_value(
							qa_db_query_sub('SELECT created FROM ^users
												WHERE userid = #',
												$userid),
											true);
			$dp_start = substr($dp_start, 0, 7);
			$qa_content['custom'] .= '<script type="text/javascript">
				$(document).ready(function()
				{
					// make sure table has data
					if($(".tablesorter tr td").length>0) {
						$(".tablesorter").tablesorter(); // {sortList: [[1,0]]}
					}

					$("#datepicker").Zebra_DatePicker({
						direction: ["'.$dp_start.'", new Date().toISOString().substring(0,7)], // until today
						format: "Y-m",
						lang_clear_date: "",
						months: ["'.$monthnames[0].'", "'.$monthnames[1].'", "'.$monthnames[2].'", "'.$monthnames[3].'", "'.$monthnames[4].'", "'.$monthnames[5].'", "'.$monthnames[6].'", "'.$monthnames[7].'", "'.$monthnames[8].'", "'.$monthnames[9].'", "'.$monthnames[10].'", "'.$monthnames[11].'"],
						offset: [15,250],
						onSelect: function(view, elements) {
							$("form#datepick").submit();
						}
					});

					// see flot API: https://github.com/flot/flot/blob/master/API.md
					// $.plot($("#placeholder"), data, options);
					// $.plot($("#placeholder"), [ [[0, 0], [1, 1]] ], { yaxis: { max: 1 } });

					// for 1st and 2nd plot
					var weekdays = ["'.$weekdaynames[6].'","'.$weekdaynames[0].'","'.$weekdaynames[1].'","'.$weekdaynames[2].'","'.$weekdaynames[3].'","'.$weekdaynames[4].'","'.$weekdaynames[5].'"];

					/*var markings = [
						{ color: "#f6f6f6", yaxis: { from: 1 } },
						{ color: "#f6f6f6", yaxis: { to: -1 } },
						{ color: "#000", lineWidth: 1, xaxis: { from: 2, to: 2 } },
						{ color: "#000", lineWidth: 1, xaxis: { from: 8, to: 8 } }
					];*/

					// helper for returning the weekends in a period
					function weekendAreas(axes) {
						var markings = [],
							d = new Date(axes.xaxis.min);

						// go to the first Saturday
						d.setUTCDate(d.getUTCDate() - ((d.getUTCDay() + 1) % 7))
						d.setUTCSeconds(0);
						d.setUTCMinutes(0);
						d.setUTCHours(0);

						var i = d.getTime();

						// when we do not set yaxis, the rectangle automatically extends to infinity upwards and downwards
						do {
							markings.push({ xaxis: { from: i, to: i + 1 * 24 * 60 * 60 * 1000 }, color: "#DDD" });
							i += 7 * 24 * 60 * 60 * 1000;
						} while (i < axes.xaxis.max);

						return markings;
					}

					var options = {
						xaxis: {
							mode: "time",
							minTickSize: [1, "day"],
							/*ticks: [
								0, [ 4, "\u03c0/2" ], [ 8, "\u03c0" ],
							]*/
						},
						/*
						// http://www.jqueryflottutorial.com/how-to-make-jquery-flot-time-series-chart.html
						xaxes: [{
							mode: "time",
							tickFormatter: function(val, axis) {
								return weekdays[new Date(val).getDay()];
							},
							color: "black",
							position: "top",
							axisLabelUseCanvas: true,
							axisLabelFontSizePixels: 12,
							axisLabelFontFamily: "Verdana, Arial",
							axisLabelPadding: 5
						},
						{
							mode: "time",
							timeformat: "%m/%d",
							tickSize: [3, "day"],
							color: "black",
							position: "bottom",
							axisLabelUseCanvas: true,
							axisLabelFontSizePixels: 12,
							axisLabelFontFamily: "Verdana, Arial",
							axisLabelPadding: 10
						}],
						*/
						yaxis: {
							min: 0
						},
						series: {
						  lines: { show: true,
							lineWidth: 2,
							fill: false,
							fillColor: "rgba(255, 255, 255, 0.8)"
						  },
						  points: {
							show: true,
						  },
						  shadowSize: 3,
						},
						//bars: { show: true, barWidth: 0.5, fill: 0.9 },
						grid: {
							hoverable: true,
							clickable: true,
							markings: weekendAreas,
							backgroundColor: { colors: [ "#fff", "#eee" ] },
							//markings: markings
						},
						/*
						zoom: {
							interactive: true
						},
						pan: {
							interactive: true
						},
						*/
						/*legend: {
							position: "ne",
							show: true
						},*/
					}
					// for tooltip, i.e. label on hover: http://people.iola.dk/olau/flot/examples/interacting.html

					var plotData = [{ data: ['.$jsData_forTableQ.'],
										color: "#34F",
										grid: {
											show: true,
										}
									},
									{ data: ['.$jsData_forTableA.'],
										color: "rgba(255, 100, 100, 0.8)",
									},
									{ data: ['.$jsData_forTableC.'],
										color: "rgba(190, 190, 190, 0.5)",
										grid: {
											show: true,
										}
									},
								];

					$.plot($("#placeholder"), plotData, options);


					// show tooltip with data on mouseover
					/*
					function showTooltip(x, y, contents) {
						$("<div id=\'flotTooltip\'>" + contents + "</div>").css({
							position: "absolute",
							display: "none",
							top: y + 5,
							left: x + 5,
							border: "1px solid #fdd",
							padding: "2px",
							"background-color": "#fee",
							opacity: 0.80
						}).appendTo("body").fadeIn(200);
					}
					var previousPoint = null;
					$("#placeholder").bind("plothover", function (event, pos, item) {
						if (item) {
							//console.log(previousPoint + "!=" + item.dataIndex);
							//console.log(item.datapoint[0].toFixed(2) + " + " + item.datapoint[0].toFixed(2));
							if (previousPoint != item.dataIndex) {

								previousPoint = item.dataIndex;

								$("#flotTooltip").remove();
								var x = item.datapoint[0].toFixed(2),
									y = item.datapoint[1].toFixed(2);

								// showTooltip(item.pageX, item.pageY, item.series.label + " of " + x + " = " + y);
								showTooltip(item.pageX, item.pageY, ">" + x +": " + y);
							}
						} else {
							$("#flotTooltip").remove();
							previousPoint = null;
						}
					});
					*/


					/* 2nd plot */
					var options2 = {
						xaxis: {
							// mode: "day",
							// minTickSize: 1,
							zoomRange: [0.1, 10],
							panRange: [-10, 10],
							tickDecimals: 0,
						    tickFormatter: function (val, axis) {
							  return weekdays[val-1];
						    }
						},
						yaxis: {
							mode: "time",
							timeformat: "%H:%M",
							min: 0,
							max: 24*60*60*1000,
							// tickSize: 1,
							// tickDecimals: 0,
							minTickSize: [1, "hour"],
							zoomRange: [0.1, 10],
							panRange: [-10, 10],
						},
						bars: {
							show: true,
							lineWidth: 1,
							fill: false,
							fillColor: "rgba(0, 0, 255, 0.8)"
						},
						grid: {
							show: true,
							hoverable: true,
							clickable: true,
							color: "#474747",
							tickColor: "#474747",
							borderWidth: 1,
							autoHighlight: true,
							mouseActiveRadius: 2,
						},
						// zoom + pan: http://www.flotcharts.org/flot/examples/navigate/index.html
						/*zoom: {
							interactive: true
						},
						pan: {
							interactive: true
						}*/
					}

					/*

					var plotData2 = [
									{ data: ['.$jsData_forTableQ2.'],
										color: "#34F",
										grid: {
											show: true,
										}
									},
								];

					'.(isset($userhandle) ? '' :
						'$.plot($("#placeholder2"), plotData2, options2);').'

					*/

				}); // end ready
			</script>';


			$qa_content['custom'] .= '
			<style type="text/css">
				.qa-main {
					width:100%;
					max-width:850px;
				}
				#datepick {
					display:block;
					margin-bottom:20px;
				}
				#datepicker {
					font-size:14px;
					width:80px;
					padding:3px 3px 3px 3px;
					border:1px solid #DDD;
					cursor:pointer;
				}
				.tablesorter {
					/*background: #CDCDCD; */
					margin:10px 0pt 15px;
					text-align: left;
					width:100%;
					max-width:600px;
				}
				.tablesorter thead tr th {
					background: #e6EEEE;
					border: 1px solid #FFF;
					padding: 4px;
					text-align:right;
					font-weight:normal;
				}
				.tablesorter thead tr th:first-child {
					text-align:left;
				}
				.tablesorter thead tr .header {
					background:#FFC;
					/* background-image: url(bg.gif); background-repeat: no-repeat; background-position: center right; */
					cursor: pointer;
					border:1px solid #BBBBBB;
				}
				.tablesorter td {
					color: #3D3D3D;
					padding: 4px 7px 4px 4px;
					vertical-align: top;
					border: 1px solid #CCC;
					text-align:right;
				}
				.tablesorter td:first-child {
					text-align:left;
				}

				.tablesorter tr {
					/* background:#FFF; */
					border: 1px solid #CCC;
				}
				.tablesorter tr.odd td { background-color:#F0F0F6; }
				/*.tablesorter thead tr .headerSortUp { background-image: url(asc.gif); }
				.tablesorter thead tr .headerSortDown { background-image: url(desc.gif); } */
				.tablesorter thead tr .headerSortDown, .tablesorter thead tr .headerSortUp { background-color: #FFDDAA; }
				.tablesorter tr:hover td { background-color:#FFFAAA; cursor:default; }

				.userstattable {
					border-collapse:collapse;
					margin-bottom:40px;
				}
				.userstattable tr:hover {
					background:#FFF;
				}
				.userstattable th, .userstattable td {
					border:1px solid #DDD;
					text-align:left;
					vertical-align:top;
					padding:6px;
					min-width:50px;
					text-align:right;
					line-height:110%;
				}
				.userstattable th {
					font-weight:normal;
					background:#FFC;
				}
				.userstattable td:first-child {
					text-align:left;
					padding-right:10px;
				}
				.totalscore {
					background:#FFF;
				}
				.yeartable {
					margin-top:30px;
				}
				.yeartable th {
					text-align:left;
				}

				.qa-main h1 {
					margin-bottom:40px;
				}
				.qa-main h3 {
					margin-top:30px;
					font-weight:normal;
					font-size:17px;
				}

				.profileimage {
					display:inline-block;
					width:260px;
					float:right;
					vertical-align:top;
					padding:20px 0 10px 0;
					margin:40px 0 0 0;
					border:1px solid #DDE;
					background:#FFF;
					text-align:center;
				}
				.reputationlab {
					text-align:center;
					position:relative;
					min-height:70px;
					margin-top:5px;
				}
				.upro_points {
					font-size:30px;
				}
				.upro_pointslabel {
					font-size:12px;
				}
				.upro_bestanswers, .upro_upvoteds {
					position:absolute;
					top:10px;
					font-size:15px;
				}
				.upro_bestanswers {
					left:20px;
				}
				.upro_upvoteds {
					right:20px;
				}
				.upro_bestanswers_count {
					position:absolute;
					top:30px;
					left:-13px;
					width:50px;
				}
				.upro_upvoteds_count {
					position:absolute;
					top:30px;
					right:-15px;
					width:50px;
				}

				#placeholder {
					margin-bottom:50px;
				}
				#placeholder2 .tickLabel {
					margin-left:50px;
				}
				#placeholder2 .tickLabel:last-child {
					display:none;
				}
				.ludatr {
					margin:30px 0;
				}
				.ludatr input[type="text"] {
					border:1px solid #CCC;
					padding:5px;
					width:70px;
				}
				.pointshower {
					display:block;
					font-size:10px;
					color:#999;
				}

			</style>';

			// check if bestusers plugin exists and is enabled
			if(qa_opt('q2apro_bestusers_enabled'))
			{
				$monthscores = qa_db_read_all_assoc(
										qa_db_query_sub('SELECT date, points FROM ^userscores
															WHERE userid = #
																AND date > DATE_SUB(now(), INTERVAL 12 MONTH)
															ORDER BY date ASC
															',
															$userid
														)
													);
				if(count($monthscores)>0)
				{

					// init
					$alltime = '';

					$chosenYear = substr($chosenMonth, 0, 4);
					$alltime .= '
						<h3>
							'.qa_lang('q2apro_userstatistics_lang/score12m').'
						</h3>
						<p>
							'.qa_lang('q2apro_userstatistics_lang/score_hint').'
						</p>
						<p>
							'.qa_lang('q2apro_userstatistics_lang/score_hint_2').'
						</p>

						<table class="userstattable yeartable">
						<tr>
							<th>
								'.qa_lang('q2apro_userstatistics_lang/month').'
							</th>
							<th>
								'.qa_lang('q2apro_userstatistics_lang/savedscore').'
							</th>
							<th>
								'.qa_lang('q2apro_userstatistics_lang/monthscore').'
							</th>
						</tr>
					';

					$yearscore = 0;
					$lastscore = 0;
					foreach($monthscores as $score)
					{
						// $monthname = utf8_encode(strftime('%b %Y', strtotime($score['date'].' -1 month')));
						$date = new DateTime( $score['date'] );
						$interval = new DateInterval('P1M');// P[eriod] 1 M[onth]
						$date->sub($interval);
						// echo $date->format('Y-m-d');
						$monthyear = $date->format('m Y');
						// $month = $date->format('m');
						// $year = $date->format('Y');

						// $month = $monthnames[ date('m Y', strtotime($score['date'].' -1 month'))-1 ];
						// $month = $monthnames[ date('m Y', strtotime('-1 month', strtotime($score['date'].' 00:00:00')))-1 ];
						// $year = date('Y', strtotime($score['date'].' -1 month'));
						
						$alltime .= '
						<tr>
							<td>'.$monthyear.'</td>
							<td>'.$score['points'].'</td>
							<td>'.($score['points']-$lastscore).'</td>
						</tr>
						';
						$yearscore += ($score['points']-$lastscore);
						$lastscore = $score['points'];
					}
					// recent score
					$today = date('Y-m-d');
					$recentscore = qa_db_read_one_value(
											qa_db_query_sub('SELECT points FROM ^userpoints
																WHERE userid = #', $userid),
															true);
					$lastsetscore = qa_db_read_one_value(
											qa_db_query_sub('SELECT points FROM ^userscores
																WHERE userid = #
																ORDER BY date DESC
																LIMIT 1
																', $userid),
															true);
					if(is_null($lastsetscore))
					{
						$lastsetscore = 0;
					}

					$alltime .= '
					<tr style="height:30px;">
					</tr>
					<tr class="totalscore">
						<td>
							'.qa_lang('q2apro_userstatistics_lang/recenttotalscore').':
						</td>
						<td>
							'.$recentscore.'
						</td>
					</tr>
					<tr class="totalscore">
						<td>
							'.qa_lang('q2apro_userstatistics_lang/recentmonthscore').':
						</td>
						<td>
							'.($recentscore-$lastsetscore).'
						</td>
					</tr>
					';

					$alltime .= '</table>';

					$qa_content['custom'] .= $alltime;
				} // end count($monthscores)
			} // end bestusers

			return $qa_content;
		} // END process_request

		function convertTimeToSec($str_time)
		{
			$str_time = preg_replace("/^([\d]{1,2})\:([\d]{2})$/", "00:$1:$2", $str_time);
			sscanf($str_time, "%d:%d:%d", $hours, $minutes, $seconds);
			return 1000*($hours * 3600 + $minutes * 60 + $seconds); // $time_seconds
		}

	}; // END class


/*
	Omit PHP closing tag to help avoid accidental output
*/
