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
abstract class SwaleORM_Model_Abstract {
    /**
        This model's name.
    */
    public static $_modelName = NULL;
    /**
        Array of field names as you
        wish to access them from the
        model objects.  You can specify
        an object to db field mapping 
        in the mapper.  If these fields
        match the db fields either exactly
        or in a 1 to 1 ratio, then they
        can be loaded automatically with
        no additional work on your part.
    */
    public static $_fields = array();

    /**
        Array Structure: 
        associationName: array 
            type: one|many
            class: array(classNames)|className
            save: true|false
    */
    public static $_associations = array();
    
    /**
        Any virtual fields that are
        derived from some operation on 
        the database.  You must define
        a 'determine<Field>' method 
        in the appropriate query for
        each field.
    */
    public static $_virtuals = array();
 
    private $_data = array();
    private $_builder = NULL;
    private $_config = array('lazy'=>true, 'db'=>null); 

    // {{{ __construct(array $config=array())
    /**
        Define the structure of this particular model type, including fields, associations and virtual fields. 
        
        @param array $config An associative array containing this model's configuration.
    */
    public function __construct(array $config=array())  {
        if(isset($config['db'])) {
            if(!($config['db'] instanceof Zend_Db_Adapter_Abstract)) {
                throw new InvalidArgumentException('"db" must be an instance of Zend_Db_Adapter_Abstract.');
            }
            $this->_config['db'] = $config['db'];
        }


        // If lazy is not set to false, then allow lazy loading and set up the builder.
        if(!isset($config['lazy']) || $config['lazy'] !== false) { 
            $this->allowLazyLoad();
           
            $builderClass = 'Application_Model_Builder_' . static::$_modelName;
            if(class_exists($builderClass)) {
                $builder = new $builderClass(array('db'=>$this->_config['db'])); 
            } else if(class_exists('Application_Model_Builder_Base')) {
                $builder = new Application_Model_Builder_Base(array('modelName'=>static::$_modelName), array('db'=>$this->_config['db']));
            } else {
                $builder = new SwaleORM_Builder_Base(static::$_modelName, array('db'=>$this->_config['db']));
            } 
            $this->setBuilder($builder);
        }
    }

    // }}}

    // {{{ __get($name)
    /**
        Automagically retrieve field's values and perform lazy loading if
        allowed and necessary.

        @param string $name The name of the field being retrieved.
        @return mixed The value of the magically retrieved field.
    */	
    public function __get($name) {
        // If the name exists in the fields array, then this is a primary
        // model field that is not lazily loaded from the database. The 
        // model is either loaded or it is not and we can just return it.
        if(in_array($name, static::$_fields)) {
            return $this->_data[$name];

        // For both virtual fields (built from a database call) and
        // associations, we can do lazy loading.  First we need to check to
        // see wether lazy loading is enabled for this model instance.  If it
        // is, then we can attempt to load the values from the database using
        // the model's builder.
        } else if(array_key_exists($name, static::$_associations) || in_array($name, static::$_virtuals)) {
            if($this->loadLazy() && $this->_data['id'] !== NULL) {
                $this->getBuilder()->build($name, $this);
            }
            return $this->_data[$name];

        // If we haven't found the member we're looking for yet, then we need
        // to throw an exception.
        } else {
            throw new DomainException('Attempt to get non-existent member - ' . $name . ' - in model: ' . static::$_modelName);
        }
    }

    // }}}
    // {{{ __set($name, $value)
    /**
        Automagically assign a value to a field, association or virtual field.  In the
        case of an association, check for expected types as defined in the constructor.

        @param string $name The name of the field, association or virtual field to set.
        @param string $value The value to assign to that field, association or virtual field.

        @return void
    */
    public function __set($name, $value) {
        // If $name exists in the static::$_fields array then this is a simple
        // model field and we don't need to do anything special. If it exists
        // in the virtuals array, then this is a virtual model field.  We
        // don't need to do anything special for virtuals when setting.   
        if(in_array($name, static::$_fields) || in_array($name, static::$_virtuals)) {
            $this->_data[$name] = $value;

        // If $name is a key in the static::$_associations array then this is an
        // associated model.  We have expected types that we'll need to ensure
        // it matches before we can set it.
        } else if(array_key_exists($name, static::$_associations)) {

            // If this is a many association (many to many or many to one)
            // then the value must be an array.
            if(static::$_associations[$name]['type'] == 'many') {
                if(!is_array($value)) {
                    throw new RuntimeException($name . ' must be an array in ' . static::$_modelName);
                } else {

                    // Check each value in the $value array against the class (or classes)
                    // contained in the associations array.  If it doesn't match, throw
                    // an exception. 
                    if(!empty(static::$_associations[$name]['class'])) {
                        foreach($value as $v) {
                            if(is_array(static::$_associations[$name]['class'])) {
                                $found = false;
                                foreach(static::$_associations[$name]['class'] as $class) {
                                    if($v instanceof $class) {
                                        $found = true;
                                        break;
                                    }
                                }
                                
                                if(!$found) {
                                    throw new InvalidArgumentException(get_class($v) . ' is not a valid class for ' . $name);
                                }
                            } else {
                                if(!($v instanceof static::$_associations[$name]['class'])) {
                                    throw new InvalidArgumentException(get_class($v) . ' is not a valid class for ' . $name);
                                }
                            }
                        }
                    }
    
                    // If we reach here with out throwing an exception, then the array is valid. 
                    $this->_data[$name] = $value;
                }

            
            } else if(static::$_associations[$name]['type'] == 'one') {
                // Perform type checking on the value.
                if(isset(static::$_associations[$name]['class'])) {
                    if(is_array(static::$_associations[$name]['class'])) {
                        $found = false;
                        foreach(static::$_associations[$name]['class'] as $class) {
                            if($value instanceof $class) {
                                $found = true;
                                break;
                            } 
                        }
                        
                        if(!$found) {
                            throw new InvalidArgumentException(get_class($value) . ' is not a valid class for ' . $name);
                        }
                    } else {
                        if(!($value instanceof static::$_associations[$name]['class'])) {
                            throw new InvalidArgumentException(get_class($value) . ' is not a valid class for ' . $name);
                        }
                    }
                }
                // If we get here with out throwing an exception
                // then we've passed the type checks.
                $this->_data[$name] = $value;
            } else {
                throw new DomainException(static::$_associations[$name]['type'] . ' is not a valid type.  Please enter either "one" or "many".');
            }
        } else {
            throw new OutOfBoundsException('Attempt to set non-existent member - ' . $name . ' - in model: ' . static::$_modelName);
        } 
    }		

    // }}}

    // {{{ getAll():                                                        public array
    /**
        Get an array of this model's fields.  No associations or virtual fields will be included.

        @return array The base model data, minus any associations or virtual fields.
    */
    public function getAll() {
        $data = array();
        foreach(static::$_fields as $field) {
            $data[$field] = $this->_data[$field];
        }
        return $data;
    }
    
    // }}}
    // {{{ setAll(array $data):                                             public void
    /**
        Set this model's fields from an array.  Any associations or virtual fields will be ignored.

        @param array $data The base model data, minus any associations or virtual fields.
    */
    public function setAll(array $data) {
        foreach(static::$_fields as $field) {
            if(isset($data[$field])) {
                $this->_data[$field] = $data[$field]; 
            }
        } 
    }	

    // }}}

    // {{{ ensureSafeLoad():                                                public void
    /**
        Run by the builder prior to attempting a lazy load.  Intended to catch an attempt at lazy
        loading under dangerous conditions: when lazy loading should be disallowed.
    */
	public function ensureSafeLoad() {
        if(!$this->loadLazy()) {
            throw new RuntimeException('Attempt to lazy load when lazy loading is off in model ' . static::$_modelName);
        }
    }

    // }}}
    // {{{ loadLazy():                                                      public boolean

	protected function loadLazy() {
		return $this->_config['lazy'];
	}

    // }}}
    // {{{ allowLazyLoad():                                                 public void
	
	protected function allowLazyLoad() {
		$this->_config['lazy'] = true;
	}
    
    // }}}

    // {{{ getBuilder():                                                    public Application_Model_Builder_{$modelName}
	
	protected function getBuilder() {
		return $this->_builder;
	}
    
    // }}}
    // {{{ setBuilder(Application_Model_Builder_Abstract $builder):         public $this
	
	protected function setBuilder(SwaleORM_Builder_Base $builder) {
		$this->_builder = $builder;
		return $this;
	}

    // }}}		

}

?>
