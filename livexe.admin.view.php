<?php
	/**
	 * @class  livexeAdminView
	 * @author NHN (developers@xpressengine.com)
	 * @brief  livexe 모듈의 admin view class
	 **/

	class livexeAdminView extends livexe {

		/**
		 * @brief 초기화
		 *
		 * livexe 모듈은 일반 사용과 관리자용으로 나누어진다.\n
		 **/
		function init() {
			// module_srl이 있으면 미리 체크하여 존재하는 모듈이면 module_info 세팅
			$module_srl = Context::get('module_srl');
			if(!$module_srl && $this->module_srl) {
				$module_srl = $this->module_srl;
				Context::set('module_srl', $module_srl);
			}

			// module model 객체 생성 
			$oModuleModel = &getModel('module');

			// module_srl이 넘어오면 해당 모듈의 정보를 미리 구해 놓음
			if($module_srl) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
				if(!$module_info) {
					Context::set('module_srl','');
					$this->act = 'dispLivexeAdminIndex';
				} else {
					ModuleModel::syncModuleToSite($module_info);
					$this->module_info = $module_info;
					Context::set('module_info',$module_info);
				}
			}

			if($module_info && $module_info->module != 'livexe') return $this->stop("msg_invalid_request");

			// 모듈 카테고리 목록을 구함
			$module_category = $oModuleModel->getModuleCategories();
			Context::set('module_category', $module_category);

			$template_path = sprintf("%stpl/",$this->module_path);
			$this->setTemplatePath($template_path);

		}

		/**
		 * @brief liveXE 관리 목록 보여줌
		 **/
		function dispLivexeAdminIndex() {
			// 등록된 liveXE 모듈을 불러와 세팅
			$args->sort_index = "module_srl";
			$args->page = Context::get('page');
			$args->list_count = 20;
			$args->page_count = 10;
			$args->module = 'livexe';
			$output = executeQueryArray('livexe.getLiveXEList', $args);

			// 템플릿에 쓰기 위해서 context::set
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('live_list', $output->data);
			Context::set('page_navigation', $output->page_navigation);

			// 템플릿 파일 지정
			$this->setTemplateFile('index');
		}

		/**
		 * @brief liveXE 추가 폼 출력
		 **/
		function dispLivexeAdminInsert() {
			// 스킨 목록을 구해옴
			$oModuleModel = &getModel('module');
			$skin_list = $oModuleModel->getSkins($this->module_path);
			Context::set('skin_list',$skin_list);

			$mskin_list = $oModuleModel->getSkins($this->module_path, "m.skins");
			Context::set('mskin_list', $mskin_list);

			// 레이아웃 목록을 구해옴
			$oLayoutModel = &getModel('layout');
			$layout_list = $oLayoutModel->getLayoutList();

			Context::set('layout_list', $layout_list);

			$mobile_layout_list = $oLayoutModel->getLayoutList(0,"M");
			Context::set('mlayout_list', $mobile_layout_list);

			// 템플릿 파일 지정
			$this->setTemplateFile('insert');
		}

		/**
		 * @brief liveXE 삭제 화면 출력
		 **/
		function dispLivexeAdminDelete() {
			if(!Context::get('module_srl')) return $this->dispLivexeAdminIndex();
			if($this->module_info->module!='livexe') return new Object(-1,'msg_invalid_request');

			// 템플릿 파일 지정
			$this->setTemplateFile('delete');
		}

		/**
		 * @brief 권한 목록 출력
		 **/
		function dispLivexeAdminGrantInfo() {
			// 공통 모듈 권한 설정 페이지 호출
			$oModuleAdminModel = &getAdminModel('module');
			$grant_content = $oModuleAdminModel->getModuleGrantHTML($this->module_info->module_srl, $this->xml_info->grant);
			Context::set('grant_content', $grant_content);

			$this->setTemplateFile('grant_list');
		}

		/**
		 * @brief 스킨 정보 보여줌
		 **/
		function dispLivexeAdminSkinInfo() {
			// 공통 모듈 권한 설정 페이지 호출
			$oModuleAdminModel = &getAdminModel('module');
			$skin_content = $oModuleAdminModel->getModuleSkinHTML($this->module_info->module_srl);
			Context::set('skin_content', $skin_content);

			$this->setTemplateFile('skin_info');
		}


	}
?>
