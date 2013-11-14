<?php
	/**
	 * @class  livexeAdminController
	 * @author NHN (developers@xpressengine.com)
	 * @brief  liveXE 모듈의 admin controller class
	 **/

	class livexeAdminController extends livexe{

		/**
		 * @brief 초기화
		 **/
		function init() {
		}

		/**
		 * @brief liveXE 추가
		 **/
		function procLivexeAdminInsert($args = null) {
			// module 모듈의 model/controller 객체 생성
			$oModuleController = &getController('module');
			$oModuleModel = &getModel('module');

			// liveXE 모듈의 정보 설정
			$args = Context::getRequestVars();
			$args->module = 'livexe';
			$args->mid = $args->livexe_name;
			if(!$args->popular_tag_period) $args->popular_tag_period = 30;
			unset($args->livexe_name);

			// module_srl이 넘어오면 원 모듈이 있는지 확인
			if($args->module_srl) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
				if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
			}

			// module_srl의 값에 따라 insert/update
			if(!$args->module_srl) {
				$output = $oModuleController->insertModule($args);
				$msg_code = 'success_registed';
			} else {
				$output = $oModuleController->updateModule($args);
				$msg_code = 'success_updated';
			}

			if(!$output->toBool()) return $output;

			$this->add('page',Context::get('page'));
			$this->add('module_srl',$output->get('module_srl'));
			$this->setMessage($msg_code);
		}

		/**
		 * @brief liveXE 삭제
		 **/
		function procLivexeAdminDelete() {
			$module_srl = Context::get('module_srl');

			// 원본을 구해온다
			$oModuleController = &getController('module');
			$output = $oModuleController->deleteModule($module_srl);
			if(!$output->toBool()) return $output;

			// 관련 정보들을 삭제

			$this->add('module','livexe');
			$this->add('page',Context::get('page'));
			$this->setMessage('success_deleted');
		}
	}
?>
