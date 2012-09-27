<?php

/**
 * Creates an object that will hold all the animals with their elisa results
 *
 * @author Kihara Absolomon; a.kihara@cgiar.org
 * @version 0.1
 * @since 13.04.2010
 */
class AnimalElisaResults{
   var $allAnimals;

   function  __construct() {
      $this->allAnimals = array();
   }

   /**
    * Adds a new animal to the collection. The collection is an associative array that uses the animal id as the key. though this is repetition
    * as the animal object has an animal id instance, it makes searching for an animal easier
    *
    * @param object $animal   The created instance of the animal
    * @param string $animalId The animal id that will be used as a key
    */
   function addAnimal($animal, $animalId){
      $this->allAnimals[$animalId] = $animal;
   }

   /**
    * A gateway to all the animals in the object
    *
    * @return object Returns an object with all the animals
    */
   function getAnimals(){ return $this->allAnimals; }

   /**
    * A gateway to a particular animal knowing its id
    *
    * @param string $animalId The id of the animal that we are looking for
    * @return object    Returns the animal that we searched for
    */
   function getAnimalById($animalId){ return $this->allAnimals[$animalId]; }
}

/**
 * Creates an instance of the animal, its id and the diseases being tested.
 * The results component is a an array that will contain all the diseases that the animal will be tested for
 *
 * @author Kihara Absolomon - a.kihara@cgiar.org
 * @version 0.1
 * @since 13.04.2010
 */
class Animal{
   var $animalId, $samples, $hasClinicalSamples, $dam;

   /**
    * Initiates an instance of an animal
    *
    * @param string $animalId The animalId for the instance that we are initiating
    * @see Animal
    */
   function  __construct($animalId) {
      $this->samples = array();
      $this->animalId = $animalId;
      $this->hasClinicalSamples = 0;
      $this->dam = NULL;
   }

   /**
    * Adds a sample with the different types of results
    * @param object $sample
    */
   function addSample($sample, $sampleName){
      $this->samples[$sampleName] = $sample;
   }

   /**
    * Fetchs all the samples under this animal
    * @return array Returns an array of all the samples ever derived under this animal
    */
   function getAllSamples($type=NULL){
      if($type == NULL) return $this->samples;
      else{
         $allSamples=array();
         foreach($this->samples as $tempSample){
            $visit = $tempSample->getVisitId();
            if(preg_match("/$type/", $visit) == 1) $allSamples[] = $tempSample;     //get only the sample with this kind of a visit
         }
         return $allSamples;
      }
   }

   /**
    * Fetchs all this animal's animal id
    * @return array Returns the animal id for this animal
    */
   function getAnimalId(){ return $this->animalId; }

   /**
    * Fetches a sample object given the sample name
    *
    * @param string $sampleName  The name of the sample to look out for
    * @return object  Returns the sample object if found, else returns returns null
    */
   function getSampleByName($sampleName){
      if(array_key_exists($sampleName, $this->samples)) return $this->samples[$sampleName];
      else return -1;
   }

   /**
    * Sets the hasClinicalSamples to true, meaning that clinical samples were collected from this animal
    */
   function animalHasClinicalSample(){ $this->hasClinicalSamples = 1; }

   /**
    * Determines whether this animal has some clinical samples
    * @return bool Returns whether the animal has a clinical sample
    */
   function ifAnimalHasClinicalSample(){ return $this->hasClinicalSamples; }

   /**
    * Adds the dam of this animal
    * @param object $dam This is the dam(mother) of this calf
    */
   function addDam($dam){ $this->dam = $dam;}

   /**
    * Gets the dam of the calf
    * @return object   Returns the dam of this calf as an object
    */
   function getDam(){ return $this->dam;}
}

/**
 * Class sample will hold each sample, the type of tests carried out on this sample and their results
 *
 * @author Kihara Absolomon - a.kihara@cgiar.org
 * @version 0.1
 * @since 13.04.2010
 */
class Sample{
   var $sampleName, $results, $visitId;

   /**
    * Creates a new instance of a sample
    *
    * @param string $sampleName The unique id of the sample
    */
   function  __construct($sampleName, $visitId) {
      $this->sampleName = $sampleName;
      $this->visitId = $visitId;
      $this->results = array();
   }

   /**
    * Adds a disease name and its result to this sample. Does some simple checking to avoid conflicts
    *
    * @param string $diseaseName The name of the disease that we are testing
    * @param string $result      The result of the test carried out for this disease
    * @return integer   Returns 0 if the result has been added succesfully, else it returns 1
    */
   function addResult($testName, $status, $od1, $od2, $odav, $pp1, $pp2, $var, $ppav, $visitId){
      //check that this disease is not yet assigned a result and if it is the result is the same
      $tempArray = array('status' => $status, 'od1' => $od1, 'od2' => $od2, 'odav' => $odav, 'pp1' => $pp1,
            'pp2' => $pp2, 'var' => $var, 'ppav' => $ppav);
      if(in_array($tempArray, $this->results[$testName])) return 1;
      $this->results[$testName] = $tempArray;
      return 0;
   }

   /**
    * Fetches a particular result based on the visit id and the test carried out
    *
    * @param string $visitId  The visit id that the sample was collected
    * @param string $testName The name of the test carried out on the sample
    * @return array  Returns an associative array with all the results from this sample
    */
   function getResult($testName){ return $this->results[$testName];}

   /**
    * Fetches the visit id that this sample was collected
    * @return string Returns the visit id of this sample
    */
   function getVisitId(){ return $this->visitId;}
}

/**
 * Creates a result object that will hold the results of the routine and clinical visit for each disease
 * 
 * @author Kihara Absolomon - a.kihara@cgiar.org
 * @version 0.1
 * @since 13.04.2010
 */
class Result{
   var $dam, $results;

   /**
    * The class constructor
    */
   function  __construct() {
      $this->results = array();
   }

   /**
    * Adds a result type to the results collection
    * @param string $name     The name of the result to add
    * @param string $result   The value of the result to add
    */
   function addResult($name, $result){
      if($name=='dam') $this->dam = $result;
      else $this->results[$name] = $result;
   }
}

?>
