<?php
/**
 * amoCRM Curl response
 */
namespace Ufee\AmoV4\Api;
use Ufee\AmoV4\Exceptions;

class Response
{
	private
		$query,
		$data,
		$code,
		$info,
		$error;
		
    /**
     * Constructor
	 * @param string $data
	 * @param Query $query
     */
    public function __construct($data, Query $query)
    {
		$this->query = $query;
		$this->data = $data;
		$this->info = (object)curl_getinfo($query->curl);
		$this->code = $this->info->http_code;
		
		if ($this->code === 0) {
			$this->error = curl_error($query->curl);
		}
    }
	
    /**
     * Get json decoded and validate
	 * @param bool $arr
	 * @return mixed|null
     */
	public function validated(bool $arr = false)
	{
		if (!$response = $this->parseJson($arr)) {
			throw new Exceptions\AmoException('Invalid API response (non JSON), code: '.$this->getCode(), $this->getCode());
		}
		if ($this->getCode() === 401) {
			throw new Exceptions\UnauthorizedException($response->detail, $this->getCode());
		}
		if ($this->getCode() === 400 && property_exists($response, 'detail')) {
			throw new Exceptions\ValidatorException($this->validationMessage($response), $this->getCode());
		}
		return $response;
	}

    /**
     * Build error message from response detail, validation-errors and errors.
     * amoCRM often answers with a stub detail like "Invalid request body",
     * while the real reason (e.g. "Not enough rights") is in the errors key.
	 * @param object $response
	 * @return string
     */
	private function validationMessage($response)
	{
		$msg = property_exists($response, 'detail') ? $response->detail : 'Invalid API response';
		$val_errors = $response->{'validation-errors'} ?? [];
		if ($row = current($val_errors)) {
			$msg .= ': '.json_encode($row->errors);
		}
		if (property_exists($response, 'errors') && !empty($response->errors)) {
			$msg .= ': '.json_encode($response->errors, JSON_UNESCAPED_UNICODE);
		}
		return $msg;
	}

    /**
     * Get json decoded and validate entities
	 * @param string $entity_key
	 * @return array
     */
	public function validatedEntities(string $entity_key)
	{
		$validated = $this->validated();
		if (!property_exists($validated, '_embedded')) {
			throw new Exceptions\AmoException('Invalid API response for entities, embedded not found, code: '.$this->getCode(), $this->getCode());
		}
		if (!property_exists($validated->_embedded, $entity_key) || empty($validated->_embedded->{$entity_key})) {
			throw new Exceptions\AmoException('Invalid API response for entities, '.$entity_key.' not found, code: '.$this->getCode(), $this->getCode());
		}
		return $validated->_embedded->{$entity_key};
	}
	
    /**
     * Get json decoded and validate created entities
	 * @param string $entity_key
	 * @return array
     */
	public function validatedCreatedEntities(string $entity_key)
	{
		$validated = $this->validated();
		if (!empty($validated->{'validation-errors'} ?? null) || !empty($validated->errors ?? null)) {
			throw new Exceptions\ValidatorException($this->validationMessage($validated), $this->getCode());
		}
		return $this->validatedEntities($entity_key);
	}
	
	/**
	 * Get json decoded and validate updated entity
	 * @param int|string $entity_id
	 * @return object
	 */
	public function validatedUpdatedEntity($entity_id)
	{
		$validated = $this->validated();
		if (!empty($validated->{'validation-errors'} ?? null) || !empty($validated->errors ?? null)) {
			throw new Exceptions\ValidatorException($this->validationMessage($validated), $this->getCode());
		}
		if (!property_exists($validated, 'id') || $validated->id !== $entity_id) {
			throw new Exceptions\AmoException('Invalid API response for update entity, id not found or not match, code: '.$this->getCode(), $this->getCode());
		}
		return $validated;
	}
	
    /**
     * Get json decoded and validate updated entities
	 * @param string $entity_key
	 * @return array
     */
	public function validatedUpdatedEntities(string $entity_key)
	{
		$validated = $this->validated();
		if (!empty($validated->{'validation-errors'} ?? null) || !empty($validated->errors ?? null)) {
			throw new Exceptions\ValidatorException($this->validationMessage($validated), $this->getCode());
		}
		return $this->validatedEntities($entity_key);
	}
	
    /**
     * Get response content
	 * @return string
     */
	public function getData()
	{
		return $this->data;
	}
	
    /**
     * Get json decoded content
	 * @param bool $arr
	 * @return mixed|null
     */
	public function parseJson(bool $arr = false)
	{
		return json_decode($this->data, $arr);
	}
	
    /**
     * Get response code
	 * @return integer
     */
	public function getCode()
	{
		return $this->code;
	}
	
    /**
     * Get response info
	 * @return object
     */
	public function getInfo()
	{
		return $this->info;
	}
	
    /**
     * Get response error
	 * @return string|null
     */
	public function getError()
	{
		return $this->error;
	}
}
