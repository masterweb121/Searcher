<?php
namespace Phalcon\Searcher;

use \Phalcon\Db\Column;
use	\Phalcon\Mvc\Model\Manager;
use	Phalcon\Searcher\Factories\ExceptionFactory;

/**
 * Columns validator
 * @package Phalcon\Searcher
 * @since PHP >=c
 * @version 1.0
 * @author Stanislav WEB | Lugansk <stanisov@gmail.com>
 * @copyright Stanislav WEB
 */
class Validator {

	/**
	 * The minimum value for the search
	 * @var int
	 */
	private	$_min		=	3;

	/**
	 * The maximum value for the search
	 * @var int
	 */
	private	$_max		=	128;

	/**
	 * Available columns types
	 * @var array
	 */
	public $columns	=	[
		Column::TYPE_INTEGER,
		Column::TYPE_VARCHAR,
		Column::TYPE_CHAR,
		Column::TYPE_TEXT,
		Column::TYPE_DATE,
		Column::TYPE_DATETIME,
	];

	/**
	 * Available sort types
	 * @var array
	 */
	public $sort	=	[
				'asc',
				'desc',
				'ascending',
				'descending',
			];

	/**
	 * Cast of validate
	 * @var string
	 */
	private $_cast	=	'';

	/**
	 * Verified tables & columns
	 * @var array
	 */
	public $fields	=	[];

	/**
	 * Verify transferred according to the rules
	 *
	 * @param mixed $data
	 * @param array $callbacks
	 * @param string $cast
	 * @return mixed
	 */
	public function verify($data, array $callbacks = [], $cast = '') {

		if(empty($callbacks) === true)
			return $this->fields[$cast]	=	$data;

		// Create a Closure
		$isValid = function($data) use ($callbacks, $cast) {

			if(empty($cast) === false)
				$this->_cast	=	$cast;

			foreach($callbacks as $callback)
			{
				if($this->{$callback}($data) === false)
					return false;
			}
		};

		// return boolean as such
		return $isValid($data);
	}

	/**
	 * Set minimum value for the search
	 *
	 * @param int $min value
	 * @return Validator
	 */
	public function setMin($min) {
		if(is_int($min) === false)
			$this->_min	=	(int)$min;
		else
			$this->_min	=	$min;
		return $this;
	}

	/**
	 * Set maximum value for the search
	 *
	 * @param int $max value
	 * @return Validator
	 */
	public function setMax($max) {
		if(is_int($max) === false)
			$this->_max	=	(int)$max;
		else
			$this->_max	=	$max;
		return $this;
	}

	/**
	 * Verify by not null
	 *
	 * @param string $value
	 * @return boolean
	 */
	protected function isNotNull($value) {
		if(is_null($value) === true || empty($value) === true)
			throw new ExceptionFactory('DataType', [$value, 'string']);
		return true;
	}

	/**
	 * Verify by array type
	 *
	 * @param mixed $value
	 * @throws ExceptionFactory
	 * @return boolean
	 */
	protected function isArray($value) {
		if(is_array($value) === false)
			throw new ExceptionFactory('DataType', [$value, 'array']);
		return true;
	}

	/**
	 * Verify by not empty value
	 *
	 * @param mixed $value
	 * @throws ExceptionFactory
	 * @return boolean
	 */
	protected function isNotEmpty($value) {
		if(empty($value) === false)
			return true;
		else
			throw new ExceptionFactory('Column', ['EMPTY_LIST', 'Search list will not contain empty value']);
	}

	/**
	 * Verify by min length
	 *
	 * @param string $value
	 * @throws ExceptionFactory
	 * @return boolean
	 */
	protected function isNotFew($value) {
		if(strlen(utf8_decode($value)) < $this->_min)
			throw new ExceptionFactory('InvalidLength', [$value, 'greater', $this->_min]);

		return true;
	}

	/**
	 * Verify by max length
	 *
	 * @param string $value
	 * @throws ExceptionFactory
	 * @return boolean
	 */
	protected function isNotMuch($value) {
		if(strlen(utf8_decode($value)) > $this->_max)
			throw new ExceptionFactory('InvalidLength', [$value, 'less', $this->_max]);

		return true;
	}

	/**
	 * Check if field exist in table
	 *
	 * @param array $value
	 * @throws ExceptionFactory
	 * @return boolean
	 */
	protected function isExists(array $value) {

		// validate fields by exist in tables

		foreach($value as $table => $fields) {

			// load model metaData
			$model 		=  	(new Manager())->load($table, new $table);

			$metaData 	= 	$model->getModelsMetaData();

			// check fields of table

			if(empty($not = array_diff($fields, $metaData->getAttributes($model))) === false)
				throw new ExceptionFactory('Column', ['COLUMN_DOES_NOT_EXISTS', $not, $table, $metaData->getAttributes($model)]);

			// setup clear used tables
			$columnDefines = (new $table)->getReadConnection()->describeColumns($model->getSource());

			// add using tables with model alias
			$this->fields['tables'][$model->getSource()]		=	$table;

			// checking columns & fields

			foreach($columnDefines as $column) {

				if(in_array($column->getName(), $fields) === true) {
					$this->validTypes($column);

					// add column to table collection
					$this->fields[$this->_cast][$model->getSource()][$column->getName()]	= $column->getType();
				}
			}
		}
		return true;
	}

	/**
	 * Check ordered fields
	 *
	 * @param array $ordered
	 * @throws ExceptionFactory
	 * @return boolean
	 */
	protected function isOrdered(array $ordered) {

		// validate fields by exist in tables

		foreach($ordered as $table => $sort) {

			// load model metaData
			$model 		=  	(new Manager())->load($table, new $table);

			$metaData 	= 	$model->getModelsMetaData();

			// check fields of table

			if(empty($not = array_diff(array_keys($sort), $metaData->getAttributes($model))) === false)
				throw new ExceptionFactory('Column', ['COLUMN_DOES_NOT_EXISTS', $not, $table, $metaData->getAttributes($model)]);

			// check sort clause

			$sort = array_map('strtolower', $sort);

			if(empty($diff = array_diff(array_values($sort), $this->sort)) === false)
				 throw new ExceptionFactory('Column', ['ORDER_TYPES_DOES_NOT_EXISTS', $diff]);

			if(empty($diff = array_diff($sort, $this->sort)) === false)
				throw new ExceptionFactory('Column', ['ORDER_TYPES_DOES_NOT_EXISTS', $diff]);

			$this->fields[$this->_cast][$model->getSource()]	=	$sort;
		}
		return true;
	}

	/**
	 * Check if field type support in table
	 *
	 * @param string $value
	 * @throws ExceptionFactory
	 * @return boolean
	 */
	protected function validTypes(Column $column) {

		if(in_array($column->getType(), $this->columns) === false) {
			throw new ExceptionFactory('Column', ['COLUMN_DOES_NOT_SUPPORT',  $column->getType(), $column->getName()]);
		}
		return true;
	}
}