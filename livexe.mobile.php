<?php
	getView('livexe');

	class livexeMobile extends livexeView {

		var $tabs = array();

		function init()
		{
			// 모바일 스킨 설정
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
			if(!is_dir($template_path)||!$this->module_info->mskin) {
				$this->module_info->mskin = 'default';
				$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
			}
			$this->setTemplatePath($template_path);

			$args->module_srl = $this->module_srl;
			$output = executeQuery('livexe.getRSSCount', $args);
			Context::set('total_feeds', $output->data->count);

			$output = executeQuery('livexe.getDocumentCount', $args);
			Context::set('total_articles', $output->data->count);

			if(Context::get('is_logged')) {
				$logged_info = Context::get('logged_info');
				$args->module_srl = $this->module_srl;
				$args->member_srl = $logged_info->member_srl;
				$output = executeQuery('livexe.getMyRSSCount', $args);
				Context::set('total_my_feeds', $output->data->count);
			}
		}

		function dispLivexeContent() {
			parent::dispLivexeContent();
			$this->setTemplateFile('index');
		}

		function dispLivexeFeeds() {
			parent::dispLivexeFeeds();
			$this->setTemplateFile('index');
		}

		function dispLivexeMyFeeds() {
			parent::dispLivexeMyFeeds();
			$this->setTemplateFile('index');
		}

		function dispLivexeCrawler()
		{
			$oLivexeController = &getController('livexe');

			$status = $oLivexeController->doCrawl();

			$status_args->module_srl = $this->module_info->module_srl;
			$output = executeQuery('livexe.getRSSCount', $status_args);
			$status['total_rss'] = $output->data->count;

			Context::set('status', $status);
			$this->setTemplateFile('crawler');
		}

	}
?>
