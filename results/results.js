//some javascript
var project=getVariable('project',document.location.search.substring(1));

var Main={
   ajaxParams: {successMssg: undefined, div2Update: undefined}, successMssg: undefined
};

var Results={
  updateData: function(type){
     //based on the filtering as defined by the user, lets pass this data and get the animals conforning to this criteria
     //get the form input elements and submit the data
     if(type=='export' || type=='export_master'){
        if(type=='export') $("#queryId").val('export');
        if(type=='export_master') $("#queryId").val('export_master');
        document.forms[0].submit();
//        $('#seamless_upload').src = 'seamless.php?page=results&browse=search&project='+project;
        return;
     }
     else{
        $("#queryId").val('query');
     }
     //create the query
     var params='flag=fetch_batch&'+$('#searchId').formSerialize();
     Main.ajaxParams.successMssg='Successfully updated'; Main.ajaxParams.div2Update='animals';
     notificationMessage({create: true, hide:false, updatetext: false, text: 'updating ...'});
     $.ajax({type:"POST", url:'seamless.php?page=results&browse=search&project='+project, data:params, dataType:'text', success:ajaxUpdateInterface});
  },

  /**
   * Based on the animal id, it fetches the results data for this animal
   */
  fetchAnimalData: function(animalId){
      //using the passed animal id, get the results of the samples conducted on this animal
      var params;
      Main.ajaxParams.successMssg='Successfully updated.';
      Main.ajaxParams.div2Update='right_panel';
      params='flag=animalResults&animalId='+encodeURIComponent(animalId);
      notificationMessage({ create: true, hide:false, updatetext: false, text: 'updating ...' });
      $.ajax({ type:"POST", url:'seamless.php?page=results&browse=animal&project='+project, data:params, dataType:'text', success:ajaxUpdateInterface });
  },

  /**
   * show em wat u got, ie the data from the server
   */
  updateAnimalData: function(){

  }
};