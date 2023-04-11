<?php
	
	class mailer{
		var $to, $from;
		
		function mailer(){
		}
		
		function send(){
			$this->subj = str_replace("\n", '', $this->subj);
			
			$tmp = array();
			$tmp[] = 'From: '.$this->from;
			
			$tmp = mail($this->to, $this->subj, $this->body, implode("\n", $tmp));
			if (!$tmp){ echo 'mailer error! - body: '.$this->body; exit(); }
		}
		
		function set($vars, $vals){
			$this->$vars = $vals;
		}
		function destroy($vars){
			unset($this->$vars);
		}
	}
	?>
