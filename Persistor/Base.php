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
class SwaleORM_Persistor_Base {
    private static $_instances=array();
    private $_modelName = '';
    private $_mapper = null;
    private $_associations = array();
    private $_config = array();

    // {{{ __construct($modelName, array $config=array()):           protected final

    protected final function __construct($modelName, array $config=array()) {
        $this->_modelName = $modelName;
  
        foreach($modelName::$_associations as $association=>$info) {
            $this->_associations[$association] = array('type'=>$info['type'], 'save'=>$info['save']);
        }
 
        if(isset($config['db']) && !empty($config['db'])) {
            if($config['db'] instanceof Zend_Db_Adapter_Abstract) {
                $this->_config['db'] = $config['db'];
            } else {
                throw new InvalidArgumentException('Type mismatch in ' . get_class($this) . '. "db" must be an instance of Zend_Db_Adapter_Abstract.');
            }
        } else {
            $this->_config['db'] = null;
        }
            
    }

    // }}}
    // {{{ getInstanceForModel($modelName, array $config=array()):   public static SwaleORM_Persistor

    public static function getInstanceForModel($modelName, array $config=array('db'=>null)) {
        if(!isset(self::$_instances[$modelName])) {
            $class = 'Application_Model_Persistor_' . $modelName;
            if(class_exists($class)) {
                self::$_instances[$modelName] = new $class($modelName, $config);
            } else if(class_exists('Application_Model_Persistor_Base')) {
                self::$_instance[$modelName] = new Application_Model_Persistor_Base($modelName, $config);
            } else {
                self::$_instance[$modelName] = new SwaleORM_Persistor_Base($modelName, $config);
            }
        }
        return self::$_instances[$modelName];
    }

    // }}}

    // {{{ getMapper():                                                     Application_Model_Mapper_{$this->_modelName}

    protected function getMapper() {
        if(empty($this->_mapper)) {
            $mapperName = 'Application_Model_Mapper_' . $this->_modelName;
            $config = array('db'=>$this->_config['db']);
            if(class_exists($mapperName)) {
                $this->_mapper = new $mapperName($config); 
            } else if(class_exists('Application_Model_Mapper_Base')) {
                $this->_mapper = new Application_Model_Mapper_Base($this->_modelName, $config);
            } else {
                $this->_mapper = new SwaleORM_Mapper_Base($this->_modelName, $config);
            }
        }
        return $this->_mapper;
    }

    // }}}

    // {{{ clear($model):                       public void

    public function clear($model) {
        if(!($model instanceof 'Application_Model_' . $this->_modelName)) {
            throw new InvalidArgumentException('clear() must be passed a model of type Application_Model_' . $this->_modelName);
        }

        foreach($this->_associations as $target=>$info) {
            if($info['save'] === false) {
                continue;
            }

            switch($info['type']) {
                case 'one':
                    $persistorName = 'Application_Model_Persistor_' . ucfirst($target);
                    $persistor = new $persistorName();
                    $persistor->clear($model->$target);
                    unset($persistor);                 
                    break;
                case 'many':
                    $persistorName = 'Application_Model_Persistor_' . ucfirst(SwaleORM_Inflector:singularize($target)); 
                    $persistor = new $persistorName();
                    foreach($model->$target as $t) {
                        $persistor->clear($t);
                    }
                    unset($persistor);
                    break;
            }
        }

        $this->delete($model);
    }

    // }}}
    // {{{ save($model):                        public void

    public function save($model) {
        if(!($model instanceof 'Application_Model_' . $this->_modelName)) {
            throw new InvalidArgumentException('save() must be passed a model of type Application_Model_' . $this->_modelName);
        } 

        if($model->id) {
            $this->update($model);
        } else {
            $this->insert($model);
        }

        foreach($this->_associations as $target=>$info) {
            if($info['save'] === false) {
                continue;
            }

            switch($info['type']) {
                case 'one':
                    $persistorName = 'Application_Model_Persistor_' . ucfirst($target);
                    $persistor = new $persistorName();
                    $idName = lcfirst($this->_modelName) . 'ID';
                    $model->$target->$idName = $model->id;
                    $persistor->save($model->$target);
                    unset($persistor);                 
                    break;
                case 'many':
                    $persistorName = 'Application_Model_Persistor_' . ucfirst(SwaleORM_Inflector:singularize($target)); 
                    $persistor = new $persistorName();
                    foreach($model->$target as $t) {
                        $idName = lcfirst($this->_modelName) . 'ID';
                        $t->$idName = $model->id;
                        $persistor->save($t);
                    }
                    unset($persistor);
                    break;
            }
        }
    }


    // }}}

    // {{{ delete($model):                        public void

    public function delete($model) {
        if(!($model instanceof 'Application_Model_' . $this->_modelName)) {
            throw new InvalidArgumentException('delete() must be passed a model of type Application_Model_' . $this->_modelName);
        } 
        $this->getMapper()->getDbTable()->delete($this->getMapper()->getDbTable()->getAdapter()->quoteInto('id=?', $model->id));
    }

    // }}}
    // {{{ insert($model):                        protected void
    
    protected function insert($model) {
        if(!($model instanceof 'Application_Model_' . $this->_modelName)) {
            throw new InvalidArgumentException('insert() must be passed a model of type Application_Model_' . $this->_modelName);
        } 
        $data = $this->getMapper()->toDbArray($model);
        $model->id = $this->getMapper()->getDbTable()->insert($data);
    }

    // }}}
    // {{{ update($model):                        protected void

    protected function update($model) {
        if(!($model instanceof 'Application_Model_' . $this->_modelName)) {
            throw new InvalidArgumentException('update() must be passed a model of type Application_Model_' . $this->_modelName);
        } 
        $data = $this->getMapper()->toDbArray($model);
        $this->getMapper()->getDbTable()->update($data, $this->getMapper()->getDbTable()->getAdapter()->quoteInto('id=?', $model->id));
    }

    // }}}
}

?>
