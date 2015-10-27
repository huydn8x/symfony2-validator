<?php
/*******************************************************************
    ### Example use library:

    // Include library or call from service
    use CommonBundle\Component\Validator;
    
    // Init validator
    $redirectRoute = 'product';
    $requestParams = array(
        'name' => 'test name'
    );
    $validateRules = array(
        'name' => array(
            'rules' => 'required|max_length=20',
            'message' => array(
                'Name is required',
                'Name max length 20 letters'
            )
        )
    );

    $validator = $this->get('common.validator');
    $validator->setParams($requestParams);
    $validator->setRules($validateRules);
    $validateResult = $validator->run();
    if ($validateResult['errors']) {
        return $this->redirectToRoute($redirectRoute);
    }

    ### Example call flash session form data:
	
    $validator = $this->get('common.validator');
    $formData = $validator->getFlashData(Validator::FORM_DATA);

    See more pattern in $ruleConst variable

********************************************************************/

namespace CommonBundle\Component;

use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class Validator
 *
 * Validate form data before processing
 *
 * @author huydn8x@gmail.com
 * @version 1.1
 * @since 2015
 */
class Validator
{
    const ERROR = 'error';
    const FORM_DATA = 'form_data';

    private $_container = null;
    private $_errors = array();
    private $_params = array();
    private $_rules = array();

    private $_ruleConst = array(
        'required',
        'min_length',
        'max_length',
        'is_numeric',
        'integer',
        'great_than',
        'less_than',
        'alpha',
        'alpha_numeric',
        'alpha_dash',
        'email',
        'in_array',
        'is_array',
        'format_date',
        'items_is_numeric',
        'great_than_field',
        'less_than_field',
    );

    /**
     * Constructor
     *
     * @param Symfony\Component\DependencyInjection\Container
     */
    public function __construct(Container $container)
    {
        $this->_container = $container;
    }

    /**
     * Set all request parameters
     *
     * @param mixed $params
     */
    public function setParams($params)
    {
        $this->_params = $params ? $params : array();
    }

    /**
     * Set validate rule for params
     *
     * @param array $rules
     */
    public function setRules($rules)
    {
        $this->_rules = $rules ? $rules : array();
    }

    /**
     * Run validate service
     *
     * Validate all params by rules defined and store flash form data
     *
     * @return array
     */
    public function run()
    {
        if (!$this->_params || !$this->_rules) {
            return false;
        }

        $method = null;
        $ruleOption = null;
        foreach ($this->_rules as $field => $ruleGroup) {
            $fieldRules = explode('|', $ruleGroup['rules']);
            $i = 0;

            // Parsing validate rules
            foreach ($fieldRules as $ruler) {
                if (strstr($ruler, '=') !== false) {
                    $ruleOption = explode('=', $ruler);
                    if (in_array($ruleOption[0], $this->_ruleConst)) {
                        $method = '_validate' . $this->_camelCaseFuncName($ruleOption[0]);
                    }
                } else {
                    if (in_array($ruler, $this->_ruleConst)) {
                        $method = '_validate' . $this->_camelCaseFuncName($ruler);
                    }
                }

                // Call validate
                $message = $ruleGroup['message'][$i];
                if (is_callable(array($this, $method))) {
                    if (isset($ruleOption) && $ruleOption) {
                        $result = $this->$method($field, $message, $ruleOption[1]);
                    } else {
                        $result = $this->$method($field, $message);
                    }

                    if (!$result) {
                        $this->_errors[$field.'_'.$ruler] = $message;
                        break;
                    }
                }
                $i++;
            }
        }

        // Set flash form data for display previous form
        $this->setFlashData(self::FORM_DATA, $this->_params);

        return array(
            'errors' => $this->_errors
        );
    }

    /**
     * Set flash data
     *
     * @param string $key
     * @param mixed $value
     */
    public function setFlashData($key, $value)
    {
        $this->_container->get('session')->getFlashBag()->add($key, $value);
    }

    /**
     * Get flash data
     *
     * @param string $key
     * @return array
     */
    public function getFlashData($key)
    {
        $data = $this->_container->get('session')->getFlashBag()->get($key);
        if (!isset($data) || !$data) {
            return false;
        }
        return $data[0];
    }

    /**
     * Validate required field
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateRequired($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }
        if (!isset($this->_params[$field])) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }

        if(is_string($this->_params[$field]) && ltrim($this->_params[$field]) === ''){
            $this->setFlashData(self::ERROR, $message);
            return false;
        }

        if(is_array($this->_params[$field]) && empty($this->_params[$field])){
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate min length of field
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateMinLength($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        if (isset($this->_params[$field]) && $this->_getStrLengthEncoding($this->_params[$field]) < (int)$param) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate max length of field
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateMaxLength($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        if (isset($this->_params[$field]) && $this->_getStrLengthEncoding($this->_params[$field]) > (int)$param) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate field value is numeric
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateIsNumeric($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        if (isset($this->_params[$field]) && !is_numeric($this->_params[$field])) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate field value is integer
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateInterger($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        if (isset($this->_params[$field]) && !is_int($this->_params[$field])) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate field value great than something
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateGreatThan($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        if (isset($this->_params[$field]) && mb_strlen($this->_params[$field]) !== 0) {
            $origin = $this->_params[$field];
            if (($origin <= $param) === true) {
                $this->setFlashData(self::ERROR, $message);
                return false;
            }
        }
        return true;
    }

    /**
     * Validate field value less than something
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateLessThan($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        if (isset($this->_params[$field]) && mb_strlen($this->_params[$field]) !== 0) {
            $origin = $this->_params[$field];
            if (($origin >= $param) === true) {
                $this->setFlashData(self::ERROR, $message);
                return false;
            }
        }
        return true;
    }

    /**
     * Determine if the provided value contains only alpha characters.
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateAlpha($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        $regex = '/^([a-zÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i';

        if (isset($this->_params[$field]) && (!preg_match($regex, $this->_params[$field]) !== false)) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Determine if the provided value contains only alpha-numeric characters.
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateAlphaNumeric($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        $regex = '/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ])+$/i';

        if (isset($this->_params[$field]) && (!preg_match($regex, $this->_params[$field]) !== false)) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Determine if the provided value contains only alpha characters with dashed and underscores.
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateAlphaDash($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        $regex = '/^([a-z0-9ÀÁÂÃÄÅÇÈÉÊËÌÍÎÏÒÓÔÕÖÙÚÛÜÝàáâãäåçèéêëìíîïðòóôõöùúûüýÿ_-])+$/i';

        if (isset($this->_params[$field]) && (!preg_match($regex, $this->_params[$field]) !== false)) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate email address
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateEmail($field, $message, $param = null)
    {
        if (!$field) {
            return false;
        }

        if (phpversion() >= '5.2.0') {
            if (isset($this->_params[$field]) && filter_var($this->_params[$field], FILTER_VALIDATE_EMAIL) === false) {
                $this->setFlashData(self::ERROR, $message);
                return false;
            }
        } else {
            $regex = "^[a-zA-Z0-9_.+-]+@[a-zA-Z0-9-]+\.[a-zA-Z0-9-.]+$";
            if (isset($this->_params[$field]) && !preg_match($regex, $this->_params[$field])) {
                $this->setFlashData(self::ERROR, $message);
                return false;
            }
        }
        return true;
    }

    /**
     * Validate value in array
     *
     * @param string $field
     * @param string $message
     * @param mixed $params
     * @return boolean
     */
    protected function _validateInArray($field, $message, $params)
    {
        if (!$field) {
            return false;
        }
        $arrayData = explode(',', $params);
        if (isset($this->_params[$field]) && !in_array($this->_params[$field], $arrayData)) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate format date
     *
     * @param string $field
     * @param string $message
     * @param string $format
     * @return boolean
     */
    protected function _validateFormatDate($field, $message, $format)
    {
        if (!$field || !isset($this->_params[$field])) {
            return false;
        }
        $dateFormat = date($format, strtotime($this->_params[$field]));
        if ($dateFormat !== $this->_params[$field]) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate value is array
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateIsArray($field, $message, $param = null)
    {
        if (!$field || !isset($this->_params[$field])) {
            return false;
        }
        if (is_array($this->_params[$field])) {
            return true;
        }
        $this->setFlashData(self::ERROR, $message);
        return false;
    }

    /**
     * Validate multiple items is numeric
     *
     * @param string $field
     * @param string $message
     * @param mixed $param
     * @return boolean
     */
    protected function _validateItemsIsNumeric($field, $message, $param = null)
    {
        $isCorrect = true;
        if (!$field || !isset($this->_params[$field])) {
            return false;
        }
        foreach ($this->_params[$field] as $item) {
            if (!is_numeric($item)) {
                $this->setFlashData(self::ERROR, $message);
                $isCorrect = false;
                break;
            }
        }
        return $isCorrect;
    }

    /**
     * Validate datetime field value great than other field
     *
     * @param string $field
     * @param string $message
     * @param mixed $params
     * @return boolean
     */
    protected function _validateGreatThanField($field, $message, $params)
    {
        if (!isset($this->_params[$params])) {
            return true;
        }
        $start = $this->_params[$field];
        $end = $this->_params[$params];
        if ($start <= $end) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Validate datetime field value less than other field
     *
     * @param string $field
     * @param string $message
     * @param mixed $params
     * @return boolean
     */
    protected function _validateLessThanField($field, $message, $params)
    {
        if (!isset($this->_params[$params])) {
            return true;
        }
        $start = $this->_params[$field];
        $end = $this->_params[$params];
        if ($start >= $end) {
            $this->setFlashData(self::ERROR, $message);
            return false;
        }
        return true;
    }

    /**
     * Build function name to camel case format
     *
     * @param string $name
     * @return string
     */
    private function _camelCaseFuncName($name)
    {
        $funcName = '';
        if (strstr($name, '_') !== false) {
            $names = explode('_', $name);
            foreach ($names as $letter) {
                $funcName .= ucfirst($letter);
            }
            return $funcName;
        } else {
            return ucfirst($name);
        }
    }

    /**
     * Get correct string length when php older version do not support
     *
     * @param string $string
     * @return int
     */
    private function _getStrLengthEncoding($string)
    {
        $encoding = mb_detect_encoding($string);
        return mb_strlen($string, $encoding);
    }
}