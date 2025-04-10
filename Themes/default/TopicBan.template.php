<?php

//Display the topic ban form template.
function template_main()
{
	global $context, $settings, $scripturl, $txt;

	echo '
		<div class="cat_bar">
			<h3 class="catbg">
				<span class="ie6_header floatleft"><img src="', $settings['images_url'], '/edit.gif" alt="" class="icon" />', $txt['ban_topic_title'], ': ', $context['topicSubject'], '</span>
			</h3>
		</div>
		<span class="upperframe"><span></span></span>
		<div class="roundframe centertext">		
			<form action="' ,$scripturl, '?action=banTopic;topic=' ,$context['current_topic'], '" method="post" accept-charset="', $context['character_set'], '" onsubmit="submitonce(this);">
				<dl class="centertext">
				<dt><strong>' ,$txt['banned_users_from_topic'], '</strong>:</dt><br />
				<dd><input type="text" name="banned_username" id="banned_username" value="' ,$context['bannedUsers'], '" style="width: 29%;" /></dd><br />
				<dt><strong>' ,$txt['pm_subject'], '</strong>:</dt><br />
				<dd><input type="text" name="subject" id="subject" value="' ,$context['pmSubject'], '" style="width: 29%;" /></dd><br />
				<dt><strong>' ,$txt['pm_body'], '</strong>:</dt><br />
				<dd><textarea name="body" rows="6" cols="80" style="width: 40%;">', $context['pmBody'], '</textarea></dd><br />
				<p class="centertext"><input type="submit" value=" ', $txt['ban_topic'], ' " name="banTopic2" onclick="return submitThisOnce(this);" accesskey="s" class="button_submit" /></p>				
				<input type="hidden" name="sc" value="' ,$context['session_id'], '" />
				<input type="hidden" name="topic_id" value="' ,$context['current_topic'], '" />
				<script type="text/javascript" src="', $settings['default_theme_url'], '/scripts/suggest.js?fin20"></script>
						<script type="text/javascript"><!-- // --><![CDATA[
							var oAddbanned_username = new smc_AutoSuggest({
								sSelf: \'oAddbanned_username\',
								sSessionId: \'', $context['session_id'], '\',
								sSessionVar: \'', $context['session_var'], '\',
								sSuggestId: \'banned_username\',
								sControlId: \'banned_username\',
								sSearchType: \'member\',
								sTextDeleteItem: \'', $txt['autosuggest_delete_item'], '\',
								bItemList: false
							});// ]]></script>
              </dl>							
			</form>
		</div>
		<span class="lowerframe"><span></span></span>';
}