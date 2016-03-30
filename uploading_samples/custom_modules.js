/**
 * Main object for uploading the tray information to the dbase
 */
var Main={
   domObjects:undefined
};

var title='Lims Updater';

var SamplesUpload={
   maxFiles: 5,

   fileUpload: function(sender){
     if(sender.value!=''){
        //var newChild=$();
        //$('<input type="file" name="samples_batch" value="" onClick=\"SamplesUpload.fileUpload(this);\" width="50"/>').appendTo$('#uploads');
     }
   },
   
   saveUploads: function(){
   },

   /**
    * Checks or clears all the checkboxes with a specific identifier
    * @param <string> identifier The name of the check boxes we wanna act on
    * @param <bool> action       The action to do on the check box. true===check em, false===uncheck em
    */
   selectClearAllCheckBoxes: function(identifier, action){
      var all=document.getElementsByName(identifier), i;
      for(i=0; i<all.length; i++){
         all[i].checked=action;
      }
   },

   saveChanges: function(){
      //time to send data to the db
      var location, racks, i, selected=[], params;
      var filed=getVariable('file',document.location.search.substring(1));
      location=$('#locationsid').val();
      racks=$('input:checkbox');
      for(i=0; i<racks.length; i++){
         if(racks[i].checked===true) selected[selected.length]=racks[i].value;
      }

      params='flag=saveRacks&location='+encodeURIComponent(location)+'&racks='+encodeURIComponent(selected.join(','));
      if(Main.ajaxParams==undefined) Main.ajaxParams={};
      Main.ajaxParams.div2Update='contents';
      //var pckId=(selId===undefined)?'':'&id='+selId;
      notificationMessage({create:true, hide:false, updatetext:false, text:'Saving...'});
      $.ajax({type:"POST", url:'seamless.php?page='+paged+'&file='+filed, data:params, dataType:'text', success:ajaxUpdateInterface});
   },
   
   generateTrays: function(sender, sender_name){
      if(sender.value==''){
         //delete the place holders, if we had created them, for this sample type
         return;
      }
      var t, racks=new Array(), t1, i, temp=sender.value.split(','), j, k, max, min;
      for(j=0; j<temp.length; j++){
         t=temp[j].indexOf('-')
         if(t!=-1){  //we have a range of rack numbers
            t1=temp[j].split('-');
            for(i=0; i<t1.length; i++){
               if(t1[0]=='Floating' || t1[0]=='floating'){  //we have floating rack(s)
                  racks[racks.length]='Floating';
               }
               else if(validate(t1[0], 2)==false || isNaN(t1[0]) || validate(t1[1], 2)==false || isNaN(t1[1])){
                  createMessageBox('Error! The rack numbers should be numericals only.', doNothing, false); return;
               }
               else if(!(t1[0]<t1[1])){
                  createMessageBox('Error! The lower limit of a range should be less than the upper limit.', doNothing, false); return;
               }
               min=parseInt(t1[0]); max=parseInt(t1[1]);
               for(k=min; k<max+1; k++){
                  if(!inArray(k, racks)) racks[racks.length]=k;
               }
            }
         }
         else{    //we have csv rack numbers
            if(temp[j]=='Floating' || temp[j]=='floating'){  //we have floating rack(s)
               racks[racks.length]='Floating';
            }
            else if(validate(temp[j], 2)==false || isNaN(temp[j])){
               createMessageBox('Error! The rack numbers should be numericals only.', doNothing, false); return;
            }
            else if(!inArray(k, racks)) racks[racks.length]=temp[j];
         }
      }
      //we have all the racks we gonna use, so create the place holders to enter the trays
      var content='<table border=0 width="80%"><tr><th colspan=2>&nbsp;</th><th align="left">Rack</th><th align="left">Tray Numbers</th></tr>';
      var firstRack, name;
      for(i=0; i<racks.length; i++){
         if(i==0) firstRack=sender_name+racks[i];
         if(racks[i]=='Floating') name='Floating Racks';
         else name='Rack '+racks[i];
         content+='<tr><td colspan=2>&nbsp;</td><td>'+name+'</td><td><input type="text" id="'+sender_name+racks[i]
            +'" name="'+sender_name+'" ></td><td>&nbsp;</td></tr>';
      }
      content+='</table>';
      var placeHoldId='trays_'+sender_name+'Id';
      $('#'+placeHoldId).attr({innerHTML:'<td>&nbsp;</td><td align="center">'+content+'</td><td>&nbsp;</td>'});
      showHide(placeHoldId, 'show');
      $('#'+firstRack).focus();
   },

   saveAssignedTrays: function(){
      var temp, t, t1, min, max, k, allTrays=new Array(), t2, m, n, i, j;
      var filed=getVariable('file',document.location.search.substring(1));
      //get all the sample types
      $.each($('[name=racks]'), function(m, rack){
         if(rack.value!='' || rack.value!=undefined){
            //get all the racks for this sample type
            $.each($('[name='+rack.id+']'), function(n, tray){
               if(tray.value=='' || tray.value==undefined){
                  createMessageBox('Error! Please enter all the tray information for each specified rack.', doNothing, false); return;
               }
               else{
                  temp=tray.value.split(',');
                  t2=new Array();
                  for(j=0; j<temp.length; j++){
                     t=temp[j].indexOf('-')     //get the trays to be placed in this rack
                     if(t!=-1){  //we have a range of trays
                        t1=temp[j].split('-');
                        if(validate(t1[0], 2)==false || isNaN(t1[0]) || validate(t1[1], 2)==false || isNaN(t1[1])){
                           createMessageBox('Error! The tray numbers should be numericals only.', doNothing, false); return;
                        }
                        else if(!(t1[0]<t1[1])){
                           createMessageBox('Error! The lower limit of a range should be less than the upper limit.', doNothing, false); return;
                        }
                        min=parseInt(t1[0]); max=parseInt(t1[1]);
                        for(k=min; k<max+1; k++){
                           if(inArray(k, t2)===false) t2[t2.length]=k;
                           else{
                              createMessageBox('Error! Each tray must be placed in its own rack and no tray can be placed on two racks at the same time.', doNothing, false);
                              return;
                           }
                        }
                     }
                     else{    //we have csv rack numbers
                        if(validate(temp[j], 2)==false || isNaN(temp[j])){
                           createMessageBox('Error! The rack numbers should be numericals only.', doNothing, false); return;
                        }
                        if(!inArray(temp[j], t2)) t2[t2.length]=temp[j];
                        else{
                           createMessageBox('Error! Each tray must be placed in its own rack and no tray can be placed on two racks at the same time.', doNothing, false);
                           return;
                        }

                     }//else
                  }//for
                  //now create the object for this rack
                  if(t2.length!=0) allTrays[allTrays.length]={rack:tray.id, trays:t2.join(',')};
               }//else
            });
         }
      });

      //now prep to send data to the server
      var params;

      params='flag=saveTrayAllocation&data='+encodeURIComponent($.toJSON(allTrays));
      if(Main.ajaxParams==undefined) Main.ajaxParams={};
      Main.ajaxParams.div2Update='contents';
      notificationMessage({create:true, hide:false, updatetext:false, text:'Saving...'});
      $.ajax({type:"POST", url:'seamless.php?page='+paged+'&file='+filed, data:params, dataType:'text', success:ajaxUpdateInterface});
   }
}