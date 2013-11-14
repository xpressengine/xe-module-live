<?php
	/**
	 * @class  livexe
	 * @author NHN (developers@xpressengine.com)
	 * @brief  livexe 모듈의 high class
	 **/

	class livexe extends ModuleObject {

		var $search_option = array('title','content','title_content','homepage','tag'); ///< 검색 옵션

		/**
		 * @brief 설치시 추가 작업이 필요할시 구현
		 **/
		function moduleInstall() {

			return new Object();
		}

		/**
		 * @brief 설치가 이상이 없는지 체크하는 method
		 **/
		function checkUpdate() {
			$oDB = &DB::getInstance();
			// 2011.03.10 link field 변경에 따름.
			if($oDB->isColumnExists('livexe_documents', 'link')) return true;
			return false;
		}

		/**
		 * @brief 업데이트 실행
		 **/
		function moduleUpdate() {
			$oDB = &DB::getInstance();

			// 2010.03.10 livexe_document.link 필드를 livexe_document.link_new 로 교체.
			if($oDB->isColumnExists('livexe_documents', 'link'))
			{
				$oDB->addColumn('livexe_documents', 'link_new', 'text', null, null, true);
				$output_updated = executeQuery('livexe.updateDocumentsLinks', null);
				if(!$output_updated->toBool()) return new Object(-1, 'update_failed');
				$oDB->dropColumn('livexe_documents', 'link');
			}
			return new Object(0, 'success_updated');
		}

		/**
		 * @brief 캐시 파일 재생성
		 **/
		function recompileCache() {
		}

	}
?>
