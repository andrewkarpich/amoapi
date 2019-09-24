<?php
/**
 * amoCRM API client Base service method
 */
namespace Ufee\Amo\Base\Methods;
use Ufee\Amo\Api,
	Ufee\Amo\Base\Collections\Collection;

class Post extends Method
{
	protected
		$method = 'post';
	
    /**
     * Call api method
	 * @param array $arg
	 * @return Collection
     */
	public function call($post_data = [], $arg = [])
	{
		$query = new Api\Query($this->service->instance, get_class($this->service));
		$query->setUrl($this->url);
		$query->setMethod('POST');
		$query->setPostData($post_data);
		$query->setArgs(
			array_merge($this->service->api_args, $this->args, $arg)
		);
		$query->execute();
		while ($query->response->getCode() == 429) {
			sleep(1);
			$query->execute();
		}
		return $this->parseResponse(
			$query, $post_data
		);
	}
	
    /**
     * Parse api response
	 * @param Query $query
	 * @return Collection
     */
    protected function parseResponse(Api\Query &$query, $postData)
    {
		if (!$response = $query->response->parseJson()) {
			throw new \Exception('Invalid API response (non JSON), code: '.$query->response->getCode(), $query->response->getCode());
		}
		if (!isset($response->_embedded->items)) {
			if (isset($response->error)) {
				throw new \Exception('API response error (code: '.$query->response->getCode().') '.$response->error, $query->response->getCode());
			}
			if (isset($response->title) && isset($response->detail)) {
				throw new \Exception('API response error (code: '.$query->response->getCode().'), '.$response->detail, $query->response->getCode());
			}
			if (!in_array($query->response->getCode(), [200, 204])) {
				if (isset($response->response) && isset($response->response->error)) {
					throw new \Exception('Invalid API response ('.$this->service->entity_key.': items not found) - '.strval($response->response->error), $query->response->getCode());
				}
				throw new \Exception('Invalid API response ('.$this->service->entity_key.': items not found), code: '.$query->response->getCode(), $query->response->getCode());
			}
		}
		if (!empty($response->_embedded->errors)) {
			throw new \Exception('API response errors: '.json_encode($response->_embedded->errors, JSON_UNESCAPED_UNICODE), $query->response->getCode());
		}
		$result = new Collection();
		if (isset($response->_embedded->items)) {
			foreach ($response->_embedded->items as $index => $raw) {
				if (is_array($raw)) {
					foreach ($raw as $raw_item) {
						$raw_item->{'query_hash'} = $query->generateHash();
						$result->push($raw_item);		
					}
				} else if (is_object($raw)) {
					$raw->{'query_hash'} = $query->generateHash();
                    if(isset($raw->request_id, $postData['add']) && $raw->request_id === 0) $raw->request_id = $postData['add'][ $index]['request_id'];
					$result->push($raw);				
				}
			}
		}
		return $result;
	}
}
