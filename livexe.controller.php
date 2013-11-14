<?php
	/**
	 * @class  livexeController
	 * @author NHN (developers@xpressengine.com)
	 * @brief  livexe 모듈의 Controller class
	 **/

	class livexeController extends livexe {

		var $content_max_length = 65535;

		/**
		 * @brief 초기화
		 **/
		function init() {

		}

		/**
		 * @brief liveXE 30일 내의 인기 태그 추출
		 **/
		function makeTagCache($module_srl, $list_cnt = 15, $period = 30) {
			$cache_file = sprintf('%sfiles/cache/liveXE/%d.txt', _XE_PATH_,$module_srl);
			if(!file_exists($cache_file)||filemtime($cache_file)<time()-60*5) {
				$args->module_srl = $module_srl;
				$args->list_count = $list_cnt;
				$args->date = date("YmdHis", time()-60*60*24*$period);
				$output = executeQueryArray('livexe.getPopularTags',$args);
				if(!$output->toBool() || !$output->data) return array();

				$tags = array();
				$max = 0;
				$min = 99999999;
				foreach($output->data as $key => $val) {
					$tag = trim($val->tag);
					if(!$tag) continue;
					$count = $val->count;
					if($max < $count) $max = $count;
					if($min > $count) $min = $count;
					$tags[] = $val;
				}

				$mid2 = $min+(int)(($max-$min)/2);
				$mid1 = $mid2+(int)(($max-$mid2)/2);
				$mid3 = $min+(int)(($mid2-$min)/2);

				$output = null;

				foreach($tags as $key => $item) {
					if($item->count > $mid1) $rank = 1;
					elseif($item->count > $mid2) $rank = 2;
					elseif($item->count > $mid3) $rank = 3;
					else $rank= 4;

					$tags[$key]->rank = $rank;
				}
				shuffle($tags);
				FileHandler::writeFile($cache_file, serialize($tags));
			} 

			$tags = unserialize(FileHandler::readFile($cache_file));
			return $tags;
		}

		/**
		 * @brief 등록된 RSS 들에 대해 Crawling 시도
		 * 1시간에 한번이상 호출되지 않도록 강제로 자체 설정을 함
		 **/
		function doCrawl($called_http = true) {
			$result = array();
	
			// 10분 단위로 크롤링 요청 (웹요청일 경우)
			if($called_http) {
				$tmpFile = sprintf('%sfiles/cache/liveXE/update', _XE_PATH_);
				if(file_exists($tmpFile)) {
					$result['latest'] = filemtime($tmpFile);
					if(filemtime($tmpFile) + 1 > time()) return $result;
					//if(filemtime($tmpFile) + 60*10 > time()) return $result;
				}
			}

			$args->sort_index = 'last_update';
			$args->order_type = 'desc';
			$args->page = 1;
			$args->list_count = 20;
			$args->s_member_srl = 0;
			$args->cur_time = time();

			while(true) {
				$output = executeQueryArray('livexe.getRssList', $args);

				if(count($output->data)) {
					foreach($output->data as $key => $item) {
						$result['rss_count']++;
						$status = $this->_get($item);
						$result['items'] += $status['items'];
						$result['tags'] += $status['tags'];
						$result['images'] += $status['images'];
					}
				}

				if($output->total_page <= $output->page) break;
				$args->page ++;
			}

			if($called_http) {
				FileHandler::writeFile($tmpFile, '1');
				$result['latest'] = @filemtime($tmpFile);
			} else {
				$result['latest'] = time();
			}

			return $result;
		}

		function doUpdateRssCrawlerTime($livexe_rss_srl) {
			$update_args->livexe_rss_srl = $livexe_rss_srl;
			$update_args->crawler_time = time() + 60*60*3;
			executeQuery('livexe.updateRSSPeriod', $update_args);
		}

		function _get($item) {
			$status = array('items' => 0, 'tags' => 0, 'images' => 0);

			$body = Context::convertEncodingStr(FileHandler::getRemoteResource($item->rss_url, null, 3, 'GET', 'application/xml', array('User-Agent'=>'liveXE ( '.Context::getRequestUri().' )')));
			if(!$body) {
				$this->doUpdateRssCrawlerTime($item->livexe_rss_srl);
				return $status;
			}

			$body = $this->_checkAndCorrectEncodingInPI($body);

			$data = $this->parseRss($body);

			if(!$data || !count($data)) {
				$this->doUpdateRssCrawlerTime($item->livexe_rss_srl);
				return $status;
			}

			$items = array();
			for($i=0,$c=count($data);$i<$c;$i++) {
				unset($get_args);
				$get_args->module_srl = $item->module_srl;
				$get_args->link = $data[$i]->link;
				$output = executeQuery('livexe.getLiveDocumentExists', $get_args);
				if($output->data) continue;
				$items[$data[$i]->link] = $data[$i];
			}

			if(!count($items)) {
				$this->doUpdateRssCrawlerTime($item->livexe_rss_srl);
				return $status;
			}

			$gap = $start = $end = null;
			foreach($items as $link => $obj) {
				unset($args);
				$args->module_srl = $item->module_srl;
				$args->livexe_rss_srl  = $item->livexe_rss_srl;
				$args->livexe_document_srl = getNextSequence();
				$args->member_srl = $item->member_srl;
				$args->author = $obj->author;
				$args->title = $obj->title;
				$args->content = $obj->content;
				$args->link = $obj->link;
				if(count($obj->tags)) {
					$_tag = array();
					foreach($obj->tags as $key => $val) {
						$val = trim(str_replace(array(' ',"\t"),'',$val));
						if(!$val) continue;
						$_tag[] = $val;
					}
					$args->tags = implode(',',$_tag);
				}
				$args->regdate = $obj->regdate;
				$args->list_order = $args->livexe_document_srl * -1;

				if(preg_match_all('/<img([^>]+)>/is', $args->content, $matches)) {
					for($i=0,$c=count($matches[1]);$i<$c;$i++) {
						if(preg_match('/"(http)([^"]+)"/i', $matches[1][$i],$match)) {
							$filename = str_replace(array('"','&amp;'),array('','&'),$match[0]);
							if($filename) {
								$target = _XE_PATH_.'files/cache/tmp/'.$args->livexe_document_srl;
								$thumbnail_name = $args->livexe_document_srl.rand(111111,333333).'.jpg';
								$path = sprintf("./files/attach/images/%s/%s", $item->module_srl,getNumberingPath($args->livexe_document_srl,3));
								FileHandler::getRemoteFile($filename, $target);
								list($width, $height, $type, $attrs) = @getimagesize($target);
								if($width>80 && $height > 80) {
									if(FileHandler::createImageFile($target, _XE_PATH_.$path.$thumbnail_name, 100,100,'jpeg','crop')) {
										$args->thumbnail = $path.$thumbnail_name;
										$status['images'] ++;
									}
									break;
								} 
								FileHandler::removeFile($target);
							}
						}
					}
				}

				$output = executeQuery('livexe.insertLiveDocument', $args);

				if(!$output->toBool()) continue;

				$status['items']++;

				if(!$start) $start = strtotime($args->regdate);
				else {
					$end = strtotime($args->regdate);
					if($end - $start > $gap) $gap = $end - $start;
					$start = $end;
				}

				if(!count($obj->tags)) continue;

				foreach($obj->tags as $tag) {
					unset($tag_args);
					$tag_args->module_srl = $item->module_srl;
					$tag_args->livexe_rss_srl = $item->livexe_rss_srl;
					$tag_args->livexe_document_srl = $args->livexe_document_srl;
					$tag_args->tag = str_replace(array(' ',"\t"),'',$tag);
					$tag_args->regdate = $args->regdate;
					$output = executeQuery('livexe.insertLiveTag', $tag_args);
					if(!$output->toBool()) $status['tags']++;
					$status['tags'] ++;
				}
			}
			$start = $args->regdate;

			$update_args->livexe_rss_srl = $item->livexe_rss_srl;
			$update_args->crawler_time = time() + $gap;
			executeQuery('livexe.updateRSSPeriod', $update_args);

			return $status;
		}

		function _replaceDescTag($matches) {
			if(strpos('<![CDATA',$matches[1])!==false) return $matches[0];
			if($matches[0]) return '<'.$matches[1].'><![CDATA['.str_replace(array('&quot;','&lt;','&gt;','&apos;','&amp;',),array('"','<','>',"'",'&'),$matches[2]).']]></'.$matches[3].'>';
		}

		function parseRss($body) {
			// php 5.2.6 또는 xml library등에 따라서 &lt; 와 같은 내용을 태그로 인식 없애버리는 버그가 있어서 미리 치환 시킴
			$body = preg_replace_callback('/<(description|link|title)>([^<]+)<\/(description|link|title)>/is', array($this, '_replaceDescTag'), $body);

			$oXml = new XmlParser();
			$doc = $oXml->parse($body);

			if($doc->rss->attrs->version == '2.0') {
				$type = 'rss2';
				$items = $doc->rss->channel->item;
			} elseif(preg_match('/atom/i',$doc->feed->attrs->xmlns)) {
				$type = 'atom';
				$items = $doc->feed->entry;
			} else return;

			// 아티클이 없을 때의 처리
			if(!$items) $items = array();

			if(!is_array($items)) {
				$clone_item = clone($items);
				unset($items);
				$items[] = $clone_item;
			}


			$output = array();
			foreach($items as $key => $val) {
				unset($obj);

				if($type == 'rss2') {

					$obj->link = trim($val->link->body);
					if($val->origlink->body) $obj->link = trim($val->origlink->body);

					$obj->title = strip_tags(trim($val->title->body));
					if(!$val->title->body) $obj->title = '제목없음';


					$obj->author = trim($val->author->body);
					if($val->{"dc:creator"}->body) $obj->author = trim($val->{"dc:creator"}->body);

					$obj->content = trim($val->description->body);
					if($val->{"content:encoded"}->body) $obj->content = trim($val->{"content:encoded"}->body);

					// 과다하게 긴 내용 절단. by Yarra
					if(mb_strlen($obj->content) > $this->content_max_length) $obj->content = mb_substr($obj->content, 0, $this->content_max_length).'...';

					$regdate = $val->pubdate->body;
					if($val->{"dc:date"}->body) $regdate = $val->{"dc:date"}->body;
					$regdate = str_replace(array('&quot;','&lt;','&gt;','&apos;','&amp;',),array('"','<','>',"'",'&'),$regdate);

					if(strtotime($regdate)>0) $obj->regdate =  date("YmdHis", strtotime($regdate));
					else $obj->regdate = substr(str_replace(array('-',':',' ','T'),'',$regdate),0,14);

					$obj->time = mktime(substr($obj->regdate,8,2), substr($obj->regdate,10,2), substr($obj->regdate,12,2), substr($obj->regdate,4,2), substr($obj->regdate,6,2), substr($obj->regdate,0,4));
					if($obj->time > time()) {
						$obj->regdate = date("YmdHis");
						$obj->time = time();
					}

					if($val->category) {
						$category = $val->category;
						if(!is_array($category)) $category = array($category);
						for($i=0,$c=count($category);$i<$c;$i++) $obj->tags[] = $category[$i]->body;
					}

				} elseif($type == 'atom') {
					
					// link 가 여러 개 있을 경우는 rel 속성이 alternate 인 것을 우선으로, 없으면 첫번째 것으로 처리
					if(is_array($val->link)){
						foreach($val->link as $key => $link)
						{
							if($link->attrs->rel == 'alternate') 
							{
								$obj->link = trim($link->attrs->href);
								break;
							}
						}
						if(!$obj->link) $obj->link = trim($val->link[0]->attrs->href);
					}
					else
						$obj->link = trim($val->link->attrs->href);

					$obj->title = strip_tags(trim($val->title->body));
					$obj->author = trim($val->author->name->body);
					$obj->content = trim($val->content->body);
					
					$regdate = $val->published->body;
					if(strtotime($regdate)>0) $obj->regdate =  date("YmdHis", strtotime($regdate));
					else $obj->regdate = substr(str_replace(array('-',':',' ','T'),'',$regdate),0,14);

					$obj->time = mktime(substr($obj->regdate,8,2), substr($obj->regdate,10,2), substr($obj->regdate,12,2), substr($obj->regdate,4,2), substr($obj->regdate,6,2), substr($obj->regdate,0,4));
					if($obj->time > time()) {
						$obj->regdate = date("YmdHis");
						$obj->time = time();
					}

					if($val->category) {
						$category = $val->category;
						if(!is_array($category)) $category = array($category);
						for($i=0,$c=count($category);$i<$c;$i++) $obj->tags[] = $category[$i]->body;
					}

				}

				if(!$obj->title) $obj->title = trim(preg_match('/.{30}/su', $obj->content, $arr) ? $arr[0].'...':$obj->content);
				if(!$obj->link) continue;

				$output[] = $obj;
			}
			return $output;
		}

		/**
		 * @brief RSS Url 등록
		 **/
		function procLivexeInsert() {
			if(!Context::get('is_logged') || !$this->grant->insert_rss) return new Object(-1,'msg_not_permitted');

			$mid = Context::get('mid');
			$title = Context::get('title');
			$homepage = Context::get('homepage');
			$rss_url = Context::get('rss_url');
			$logged_info = Context::get('logged_info');
			if(!$title || !$homepage || !$mid || !$rss_url) return new Object(-1,'msg_invalid_request');
			if(strpos($homepage,'://')===false) $homepage = 'http://'.$homepage;
			if(strpos($rss_url,'://')===false) $rss_url = 'http://'.$rss_url;

			$args->module_srl = $this->module_info->module_srl;
			$args->rss_url = $rss_url;
			$output = executeQuery('livexe.getRssUrl', $args);
			if($output->data->rss_url == $rss_url) return new Object(-1, Context::getLang('rss_url_already_registed'));


			$args->livexe_rss_srl = getNextSequence();
			$args->member_srl = $logged_info->member_srl;
			$args->title = $title;
			$args->homepage = $homepage;
			$args->regdate = date("YmdHis");
			return executeQuery('livexe.insertRss', $args);
		}

		/**
		 * @brief RSS Url 삭제
		 * 관련 글/태그도 모두 삭제 함
		 **/
		function procLivexeDelete() {
			$mid = Context::get('mid');
			$livexe_rss_srl = Context::get('livexe_rss_srl');
			if(!$mid || !$livexe_rss_srl) return new Object(-1,'msg_invalid_request');

			if(!Context::get('is_logged') || !$this->grant->insert_rss) return new Object(-1,'msg_not_permitted');

			$args->module_srl = $this->module_info->module_srl;
			$args->livexe_rss_srl = $livexe_rss_srl;
			$output = executeQuery('livexe.getRssUrl', $args);
			if(!$output->data) return new Object(-1,'msg_invalid_request');

			$logged_info = Context::get('logged_info');
			$rss_info = $output->data;
			if($rss_info->member_srl != $logged_info->member_srl) return new Object(-1,'msg_not_permitted');

			executeQuery('livexe.deleteOwnDocuments', $args);
			executeQuery('livexe.deleteOwnTags', $args);
			executeQuery('livexe.deleteRss', $args);
		}


		/**
		 * @brief rss_url 의 정보를 구해서 return
		 * title, homepage, rss_url
		 **/
		function procLivexeGet() {
			$rss_url = Context::get('rss_url');
			if(!$rss_url) return new Object(-1,'msg_invalid_request');
			if(strpos($rss_url,'://')===false) $rss_url = 'http://'.$rss_url;

			$body = Context::convertEncodingStr(FileHandler::getRemoteResource($rss_url, null, 3, 'GET', 'application/xml', array('User-Agent'=>'liveXE ( '.Context::getRequestUri().' )')));
			$body = $this->_checkAndCorrectEncodingInPI($body);

			$oXml = new XmlParser();
			$doc = $oXml->parse($body);


			if($doc->rss->attrs->version == '2.0') {
				$this->add('title',$doc->rss->channel->title->body);
				$this->add('homepage',$doc->rss->channel->link->body);
				$this->add('rss_url',$rss_url);
			} elseif(preg_match('/atom/i',$doc->feed->attrs->xmlns)) {
				$this->add('title',$doc->feed->title->body);
				if(is_array($doc->feed->link)) $this->add('homepage',$doc->feed->link[0]->attrs->href);
				else $this->add('homepage',$doc->feed->link->attrs->href);
				$this->add('rss_url',$rss_url);
			} else return new Object(-1,'msg_not_supported_rss');
		}


		// private methods from here.


		/**
		 * @brief 실제 인코딩과 XML 헤더에 선언된 인코딩이 다를 경우 헤더에 선언된 인코딩을 수정
		 */
		function _checkAndCorrectEncodingInPI($body)
		{
			// get encoding property from PI and actual content encoding.
			$encodingInPI = $this->_getEncodingInPI($body);
			$actualEncoding = $this->_getActualEncoding($body);

			if(!$encodingInPI) return $body;

			if($encodingInPI != $actualEncoding) return $this->_correctEncodingInPI($body, $actualEncoding);
			else return $body;
		}

		function _getEncodingInPI($body)
		{
			$matches = array();
			preg_match('/<\?(.+)\?>/', $body, $matches);
			$has_declared_encoding = preg_match('/encoding="(.+)"/', $matches[0], $matches);
			$declared_encoding = strtolower($matches[0]);

			if(!$has_declared_encoding) return 0;
			else return $declared_encoding;
		}

		function _getActualEncoding($body)
		{
			$converted_body = iconv('utf-8', 'utf-8', $body);
			if($body != $converted_body) return 'euc-kr';
			else return 'utf-8';
		}

		function _correctEncodingInPI($body, $actualEncoding)
		{
			$matches_1 = array();
			$matches_2 = array();
			
			$is_matched = preg_match('/<\?(.+)\?>/', $body, $matches);
			if($is_matched) $oldPI = $matches[0];
			else return $body;
			$is_matched = preg_match('/encoding="(.+)"/', $oldPI, $matches);
			if($is_matched) $oldEncoding = $matches[1];
			else return $body;
			$correctedPI = str_replace($oldEncoding, $actualEncoding, $oldPI);
			$correctedBody = preg_replace('/<\?(.+)\?>/', $correctedPI, $body, 1);

			return $correctedBody;

		}
	}
?>
