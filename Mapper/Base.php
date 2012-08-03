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

class SwaleORM_Mapper_Base {
    private $_dbTable;
    private $_modelName;
    private $_fields;
    private $_config = array();
 
    // {{{ __construct($modelName, array $config=array())

    public function __construct($modelName, array $config=array()) {
        if(!isset($modelName)) {
            throw new BadMethodCallException('Definition parameter "modelName" is a required parameter.');
        } else {
            $modelClass = 'Application_Model_' . $modelName;
            if(!class_exists($modelClass)) {
                throw new InvalidArgumentException('"modelName" must be a valid model class in application/models.'); 
            }
            $this->_modelName = $modelName;
            $this->_fields = $modelClass::$_fields;
        }

        if(isset($config['db']) && !empty($config['db'])) {
            if(!($config['db'] instanceof Zend_Db_Adapter_Abstract)) {
                throw new InvalidArgumentException('Config parameter "db" must be an instance of Zend_Db_Adapter_Abstract.');
            } else {
                $this->_config['db'] = $config['db'];
            } 
        } else {
            $this->_config['db'] = null;
        }
    }

    // }}}

    // {{{ dbNameToObjectName(string $name):                                public string
    
    public function nameFromDb($name) {
        return $name;
    }

    // }}}
    // {{{ objectNameToDbName(string $name):                                public string

    public function nameToDb($name) {
        return $name;
    }

    // }}}

    // {{{ getDbTable():                                                    public Application_Model_DbTable_{$model} 
	
	public function getDbTable() {
		if(empty($this->dbTable)) {
            $tableName = 'Application_Model_DbTable_' . $this->_modelName;
            if($this->_config['db'] !== null) {
                $this->dbTable = new $tableName(array('db'=>$this->_config['db']));
            } else {
                $this->dbTable = new $tableName();
            }
		}
		return $this->dbTable;
    } 

    // }}}
    // {{{ fromDbObject($model, $data):                                     public void
	
	public function fromDbObject($model, $data) {
		$this->fromDbArray($model, $data->toArray());
	}

    // }}}
    // {{{ fromDbArray($model, array $data):                                public void
	
	public function fromDbArray($model, array $data) {
        $data = array_map('stripslashes', $data);
        foreach($this->_fields as $field=>$info) {
            if(isset($info['mapper'])) {
                if(class_exists('Application_Model_Mapper_Field_' . $info['mapper'])) {
                    $mapperClass = 'Application_Model_Mapper_Field_' . $info['mapper'];
                    $mapper = new $mapperClass();
                } else if(class_exists('SwaleORM_Mapper_Field_' . $info['mapper'])) {
                    $mapperClass = 'SwaleORM_Mapper_Field_' . $info['mapper'];
                    $mapper = new $mapperClass();
                } else {
                        throw new InvalidArgumentException('Field mapper "' . $info['mapper'] . '" not found!');
                }
                
                $model->$field = $mapper->fromDb($data[$this->nameToDb($field)]); 
            }  
        }       
    }

    // }}}
    // {{{ toDbArray($model):                                               public array
	
	public function toDbArray($model) {
	    $data = $model->getAll();	
        return $data;
	}
    
    // }}}



}

?>
