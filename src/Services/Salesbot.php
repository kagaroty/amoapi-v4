<?php
/**
 * amoCRM API client Salesbot service
 */
namespace Ufee\AmoV4\Services;

class Salesbot extends Service
{
	protected $api_path = '/api/v4/bots';

	/** @var array */
	protected $allowed_run_entity_types = ['leads', 'contacts', 'customers'];

	/** @var array */
	protected $allowed_stop_entity_types = ['leads', 'customers'];

	/**
	 * Run salesbot tasks queue
	 * @param array<int, array{bot_id:int, entity_id:int, entity_type:string}> $tasks
	 */
	public function run(array $tasks): bool
	{
		if (empty($tasks)) {
			throw new \InvalidArgumentException('Salesbot run payload can not be empty');
		}
		if (count($tasks) > 100) {
			throw new \InvalidArgumentException('Salesbot run supports maximum 100 tasks per request');
		}

		foreach ($tasks as $task) {
			if (!is_array($task)) {
				throw new \InvalidArgumentException('Each Salesbot task must be an array');
			}
			$this->validateRunTask($task);
		}

		$query = $this->instance->query('POST', $this->api_path.'/run');
		$query->setJsonData($tasks);
		$query->execute();
		return $query->response->getCode() === 202;
	}

	/**
	 * Stop salesbot by bot id for entity
	 * @param int $bot_id
	 * @param int $entity_id
	 * @param string $entity_type
	 */
	public function stop(int $bot_id, int $entity_id, string $entity_type = 'leads'): bool
	{
		if ($bot_id <= 0) {
			throw new \InvalidArgumentException('Bot ID must be positive integer');
		}
		if ($entity_id <= 0) {
			throw new \InvalidArgumentException('Entity ID must be positive integer');
		}
		if (!in_array($entity_type, $this->allowed_stop_entity_types, true)) {
			throw new \InvalidArgumentException('Salesbot stop entity_type must be one of: leads, customers');
		}

		$query = $this->instance->query('POST', $this->api_path.'/'.$bot_id.'/stop');
		$query->setJsonData([
			'entity_id' => $entity_id,
			'entity_type' => $entity_type
		]);
		$query->execute();
		return $query->response->getCode() === 202;
	}

	/**
	 * Validate one run task payload
	 * @param array{bot_id:int, entity_id:int, entity_type:string} $task
	 */
	protected function validateRunTask(array $task): void
	{
		if (empty($task['bot_id']) || !is_int($task['bot_id']) || $task['bot_id'] <= 0) {
			throw new \InvalidArgumentException('Salesbot task bot_id must be positive integer');
		}
		if (empty($task['entity_id']) || !is_int($task['entity_id']) || $task['entity_id'] <= 0) {
			throw new \InvalidArgumentException('Salesbot task entity_id must be positive integer');
		}
		if (empty($task['entity_type']) || !is_string($task['entity_type']) || !in_array($task['entity_type'], $this->allowed_run_entity_types, true)) {
			throw new \InvalidArgumentException('Salesbot task entity_type must be one of: leads, contacts, customers');
		}
	}
}
