<?php

namespace aadutskevich\treegrid;

use Closure;
use kartik\grid\GridView;
use yii\base\InvalidConfigException;
use yii\grid\Column;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Json;

/**
 * TreeGrid renders a jQuery TreeGrid component.
 */
class TreeGrid extends GridView
{

	/**
	 * @var array the HTML attributes for the container tag of the grid view.
	 * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
	 */
	public $options = ['class' => 'table table-striped table-bordered'];

	/**
	 * @var array The plugin options
	 */
	public $pluginOptions = [];

	/**
	 * @var string name of key column used to build tree
	 */
	public $keyColumnName;

	/**
	 * @var string name of parent column used to build tree
	 */
	public $parentColumnName;

	/**
	 * @var mixed parent column value of root elements from data
	 */
	public $parentRootValue = NULL;

	/**
	 * Initializes the grid view.
	 * This method will initialize required property values and instantiate [[columns]] objects.
	 */
	public function init()
	{
		if (!$this->keyColumnName) {
			throw new InvalidConfigException('The "keyColumnName" property must be specified"');
		}
		if (!$this->parentColumnName) {
			throw new InvalidConfigException('The "parentColumnName" property must be specified"');
		}

		parent::init();
	}

	/**
	 * Runs the widget.
	 */
	public function run()
	{
		$id = $this->options['id'];
		$options = Json::htmlEncode($this->pluginOptions);
		TreeGridAsset::register($this->view);
		$this->view->registerJs("jQuery('#$id').treegrid($options);");

		parent::run();
	}


	/**
	 * Renders a table row with the given data model and key.
	 * @param mixed $model the data model to be rendered
	 * @param mixed $key the key associated with the data model
	 * @param integer $index the zero-based index of the data model among the model array returned by [[dataProvider]].
	 * @return string the rendering result
	 */
	public function renderTableRow($model, $key, $index)
	{
		$cells = [];
		/* @var $column Column */
		foreach ($this->columns as $column) {
			$cells[] = $column->renderDataCell($model, $key, $index);
		}
		if ($this->rowOptions instanceof Closure) {
			$options = call_user_func($this->rowOptions, $model, $key, $index, $this);
		} else {
			$options = $this->rowOptions;
		}
		$options['data-key'] = is_array($key) ? json_encode($key) : (string) $key;

		$id = ArrayHelper::getValue($model, $this->keyColumnName);
		Html::addCssClass($options, "treegrid-$id");

		$parentId = ArrayHelper::getValue($model, $this->parentColumnName);
		if ($parentId) {
			if (ArrayHelper::getValue($this->pluginOptions, 'initialState') == 'collapsed') {
				Html::addCssStyle($options, 'display: none;');
			}
			Html::addCssClass($options, "treegrid-parent-$parentId");
		}

		return Html::tag('tr', implode('', $cells), $options);
	}


	/**
	 * Renders the table body.
	 * @return string the rendering result.
	 */
	public function renderTableBody()
	{
		$models = array_values($this->dataProvider->getModels());
		$keys = $this->dataProvider->getKeys();
		$rows = [];
		$models = $this->normalizeData($models, $this->parentRootValue);
		foreach ($models as $index => $model) {
			$key = $keys[$index];
			if ($this->beforeRow !== NULL) {
				$row = call_user_func($this->beforeRow, $model, $key, $index, $this);
				if (!empty($row)) {
					$rows[] = $row;
				}
			}

			$rows[] = $this->renderTableRow($model, $key, $index);

			if ($this->afterRow !== NULL) {
				$row = call_user_func($this->afterRow, $model, $key, $index, $this);
				if (!empty($row)) {
					$rows[] = $row;
				}
			}
		}

		if (empty($rows)) {
			$colspan = count($this->columns);

			return "<tbody>\n<tr><td colspan=\"$colspan\">" . $this->renderEmpty() . "</td></tr>\n</tbody>";
		} else {
			return "<tbody>\n" . implode("\n", $rows) . "\n</tbody>";
		}
	}

	/**
	 * Normalize tree data
	 * @param array $data
	 * @param string $parentId
	 * @return array
	 */
	protected function normalizeData(array $data, $parentId = NULL)
	{
		$result = [];
		foreach ($data as $element) {
			if ($element[$this->parentColumnName] == $parentId) {
				$result[] = $element;
				$children = $this->normalizeData($data, $element[$this->keyColumnName]);
				if ($children) {
					$result = array_merge($result, $children);
				}
			}
		}

		return $result;
	}
}
