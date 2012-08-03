<?php
/********************************************************************************
 * 			Copyright (c) 2011 Daniel Bingham
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy 
 * of this software and associated documentation files (the "Software"), to deal 
 * in the Software without restriction, including without limitation the rights 
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell 
 * copies of the Software, and to permit persons to whom the Software is 
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in 
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR 
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE 
 * SOFTWARE.
 * 
 * ******************************************************************************/

class SwaleORM_Builder_Base {
    protected $_modelName = '';	
	protected $_targets = array();
    private $_config = array();

    // {{{ __construct($modelName, array $config)

    public function __construct($modelName, array $config) {
        if(!isset($modelName)) {
            throw new BadMethodCallException('Definition parameter "modelName" is required.');
        } else if(!class_exists('Application_Model_' . $modelName)) {
            throw new BadMethodCallException($modelName . ' is not a valid model class!');
        }
        $this->_modelName = $modelName;
       
        $modelClass = 'Application_Model_' . $this->_modelName; 
        // Pull the targets down from the model into the target array. 
        foreach($modelClass::$_associations as $association=>$info) {
            $this->_targets[$association] = array('built'=>false, 'type'=>$info['type']);
        }
        foreach($modelClass::$_virtuals as $virtual) {
            $this->_targets[$virtual] = array('built'=>false, 'type'=>'virtual'); 
        } 
    
        if(isset($config['db']) && !empty($config['db'])) {
            if($config['db'] instanceof Zend_Db_Adapter_Abstract) {
                $this->_config['db'] = $config['db'];
            } else {
                throw new InvalidArgumentException('Type mismatch in ' . get_class($this) . '.  "db" must be an instance of Zend_Db_Adapter_Abstract');
            }
        } else {
            $this->_config['db'] = null;
        }
    }

    // }}}
    // {{{ build($target, $model):                                          public void
    /**
        @param string $target The name of the association or virtual field that is targeted for building.
        @param Application_Model_Abstract The model on which the building will occur.
    */	
	public function build($target, $model) {
        // If we're given the target 'all' then loop through
        // the targets array and recursively call build on 
        // each target.  When we're done, return, nothing more to do.
        if($target == 'all') {
            foreach($this->_targets as $target=>$info) {
                $this->build($target, $model); 
            } 
            return;
        }

        // If we don't have a target of 'all', then ensure the target
        // exists.
		if(!array_key_exists($target, $this->_targets)) {
			throw new BadMethodCallException('Attempt to build non-existent target "' . $target . '" in Builder for model "' . $this->_modelName . '"');
		}
	
        // Ensure that it is safe to perform lazy loading.	
		$model->ensureSafeLoad();
	
        // If we've already loaded this target on this model, then don't.	
		if($this->_targets[$target]['built']) {
			return;
		}

        // Otherwise, record that we have.
		$this->_targets[$target]['built'] = true;
	
        // If there is an override method to perform this build, then call it.	
		$method = 'build' . ucfirst($target);
        if(method_exists($this, $method)) {
            $this->$method($model);
        
        // Otherwise we'll attempt to figure out what build we should be performing.
        } else {
            switch($this->_targets[$target]['type']) {
                case 'one':
                    $targetModelName = ucfirst($target);
                    $idField = $target . '_id';
                    $model->$target = SwaleORM_Query_Base::getInstanceForModel(
                                            $targetModelName,
                                            array('db'=>$this->_config['db'])
                                        )->get($model->$idField); 
                    break;
                case 'many':
                    $targetModelName = ucfirst(SwaleORM_Inflector::singularize($target));
                    $idField = lcfirst($this->_modelName) . '_id';
                    $model->$target = SwaleORM_Query_Base::getInstanceForModel(
                                            $targetModelName, 
                                            array('db'=>$this->_config['db'])
                                        )->fetchAll(array($idField=>$model->id));
                    break;
                case 'virtual':
                    $methodName = 'determine' . ucfirst($target);
                    $targetModelName= ucfirst($this->_modelName);
                    $model->$target = SwaleORM_Query_Base::getInstanceForModel(
                                            $targetModelName, 
                                            array('db'=>$this->_config['db'])
                                        )->$methodName($model); 
                    break;
                default:
                    throw new DomainException('Invalid type "' . $this->_targets[$target]['type'] . '" for target "' . $target . '" in Builder for model "' . $this->_modelName . '"');
                    break;
            }
        }
	}

    // }}}
	
	
}

?>
