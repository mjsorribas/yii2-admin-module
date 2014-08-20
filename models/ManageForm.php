<?php


namespace asdfstudio\admin\models;


use Yii;
use asdfstudio\admin\AdminItemInterface;
use yii\base\InvalidConfigException;
use yii\base\Model;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * Class ManageForm
 * @package asdfstudio\admin\models
 */
class ManageForm extends Model
{
    /**
     * Model
     * @var AdminItemInterface|ActiveRecord
     */
    public $model;
    /**
     * Data for model
     * @var array
     */
    public $data;
    /**
     * Attributes used for validating and saving related models
     */
    private $_adminAttributes = [];
    /**
     * List of models related to [[self::$model]]
     */
    private $_relatedModels = [];

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();

        $this->model->load($this->data);

        if (!($this->model instanceof AdminItemInterface)) {
            throw new InvalidConfigException();
        }

        $this->_adminAttributes = $this->model->adminAttributes();
        $this->_relatedModels = $this->loadRelatedModels();
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['model', 'data'], 'safe'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeValidate()
    {
        if (parent::beforeValidate()) {
            if (!$this->model->validate()) {
                return false;
            }

            foreach ($this->_relatedModels as $models) {
                foreach ($models as $model) {
                    /* @var Model $model */
                    if (!$model->validate()) {
                        return false;
                    }
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Save model with all related models
     */
    public function saveModel()
    {
        if ($this->model->save()) {
            foreach ($this->_relatedModels as $attribute => $models) {
                $this->model->unlinkAll($attribute, false);
                foreach ($models as $model) {
                    /* @var ActiveRecord $model */
                    $this->model->link($attribute, $model);
                }
            }
            return true;
        }
        return false;
    }

    /**
     * Load related models. Creating new models if not exists
     * @return array [attribute_name => [models]]
     */
    public function loadRelatedModels()
    {
        $relatedModels = [];
        $input = (isset($this->data[$this->model->formName()])) ? $this->data[$this->model->formName()] : $this->data;

        foreach ($this->_adminAttributes as $attribute) {
            $attribute = (is_array($attribute)) ? ArrayHelper::getValue($attribute, 'attribute', null) : $attribute;
            if (!$attribute) continue;

            if (method_exists($this->model, 'get' . $attribute)) {
                $query = call_user_func([$this->model, 'get' . $attribute]);
                if ($query instanceof ActiveQuery) {
                    $data = ArrayHelper::getValue($input, $attribute, []);

                    $keys = array_keys($query->link);
                    $foreignKey = $keys[0];

                    /* @var ActiveQuery $modelsQuery */
                    $modelsQuery = call_user_func([$query->modelClass, 'find']);
                    $models = $modelsQuery->where([$foreignKey => $data])->all();
                    $relatedModels[$attribute] = $models;
                } else {
                    // TODO: handle this somehow
                }
            }
        }
        return $relatedModels;
    }

    public function getRelatedModels()
    {
        return $this->_relatedModels;
    }
}