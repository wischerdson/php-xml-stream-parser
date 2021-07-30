<?php

class XMLStreamParser
{
	private $reader = null;

	private $listeners = [];

	private $stack = [];

	private $serialized = '';

	private $data = [];

	public function __construct()
	{
		$this->reader = new XMLReader();
	}

	public function open($file)
	{
		$this->reader->open($file);
	}

	public function each($element, $callback)
	{
		$this->listeners[] = [$element, $callback];
	}

	public function start()
	{
		while ($this->read([$this, 'startElement'], [$this, 'endElement']));

		$this->reader->close();
	}

	private function read($startCallback = null, $endCallback = null, $textCallback = null)
	{
		$state = $this->reader->read();

		$callback = null;
		
		switch ($this->reader->nodeType) {
			case XMLReader::ELEMENT:
				$callback = $startCallback;
				break;

			case XMLReader::END_ELEMENT:
				$callback = $endCallback;
				break;

			case XMLReader::CDATA:
			case XMLReader::TEXT:
				$callback = $textCallback;
				break;
		}

		!$callback || call_user_func($callback);

		return $state;
	}

	/* Проверка имеется ли зарегистрированный слушатель для текущего узла */
	private function hasListener()
	{
		foreach ($this->listeners as $listener) {
			if (preg_match("/(^|\.)$listener[0]$/", $this->serialized)) {
				return true;
			}
		}

		return false;
	}

	/* Получение массива атрибутов текущего узла */
	private function getAttributes()
	{
		$attributes = [];

		if ($this->reader->hasAttributes) {
			$this->reader->moveToFirstAttribute();

			do {
				$attributes[$this->reader->name] = $this->reader->value;
			} while ($this->reader->moveToNextAttribute());

			$this->reader->moveToElement();
		}

		return $attributes;
	}

	private function startElement()
	{
		echo (memory_get_usage()/1024)."\n";
		$this->stackPushElem();

		if ($this->hasListener()) {
			$this->data[$this->serialized] = [];
		}

		if (empty($this->data)) {
			return;
		}

		$attributes = $this->getAttributes();
		$node = [$this->serialized, null, $attributes];
		
		$this->read(null, null, function () use (&$node) {
			$node[1] = $this->reader->value;
		});

		foreach ($this->data as &$item) {
			$item[] = $node;
		}
		unset($item, $node);
	}

	private function endElement()
	{
		while ($this->stackPop() != $this->reader->localName) {
			if (empty($this->stack)) break;
		}

		foreach ($this->data as $key => $item) {
			if (!preg_match("/^$key/i", $this->serialized)) {
				$this->fireData($key, $this->data[$key]);
				unset($this->data[$key]);
			}
		}
	}

	private function stackPushElem()
	{
		$this->stack[] = $this->reader->localName;
		$this->serialized = implode('.', $this->stack);
	}

	private function stackPop()
	{
		$droped = array_pop($this->stack);
		$this->serialized = implode('.', $this->stack);
		return $droped;
	}

	private function optimize($element, $data)
	{
		$result = [];

		foreach ($data as $item) {
			$name = preg_replace("/$element\./i", '', $item[0]);
			$array = array_merge(['_' => $item[1]], $item[2]);
			if (in_array($name, $result)) {
				$result[$name][] = $array;
			} else {
				$result[$name] = [$array];
			}
		}

		return $result;
	}

	private function fireData($element, $data)
	{
		// $data = $this->optimize($element, $data);

		foreach ($this->listeners as $listener) {
			if (preg_match("/(^|\.)$listener[0]$/", $element)) {
				call_user_func($listener[1], $data);
			}
		}
	}
}


$xml = new XMLStreamParser();
$xml->open('file.xml');

$xml->each('yml_catalog.shop.offers.offer', function ($offer) {
	
});

$xml->start();

