<?php
if (!isConnect('admin')) {
	throw new Exception('Error 401 Unauthorized');
}
include_file('3rdparty', 'fullcalendar/fullcalendar', 'css', 'calendar');
include_file('3rdparty', 'datetimepicker/jquery.datetimepicker', 'css', 'calendar');
$plugin = plugin::byId('calendar');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
  <div class="col-lg-2 col-md-3 col-sm-4">
    <div class="bs-sidebar">
      <ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
        <a class="btn btn-default eqLogicAction" style="width : 100%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter un agenda}}</a>
        <li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
        <?php
foreach ($eqLogics as $eqLogic) {
	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
	echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '" style="' . $opacity . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
}
?>
     </ul>
   </div>
 </div>

 <div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
   <div class="eqLogicThumbnailContainer">
    <legend><i class="fa fa-cog"></i>  {{Gestion}}</legend>
    <div class="cursor eqLogicAction" data-action="add" style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
     <center>
      <i class="fa fa-plus-circle" style="font-size : 6em;color:#94ca02;"></i>
    </center>
    <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;;color:#94ca02"><center>{{Ajouter}}</center></span>
  </div>
  <div class="cursor" id="bt_healthcalendar" style="background-color : #ffffff; height : 120px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
    <center>
      <i class="fa fa-medkit" style="font-size : 6em;color:#767676;"></i>
    </center>
    <span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Santé}}</center></span>
  </div>
</div>
<legend><i class="fa fa-calendar"></i> {{Mes agendas}}
</legend>
<div class="eqLogicThumbnailContainer">
  <?php
foreach ($eqLogics as $eqLogic) {
	$opacity = ($eqLogic->getIsEnable()) ? '' : jeedom::getConfiguration('eqLogic:style:noactive');
	echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;' . $opacity . '" >';
	echo "<center>";
	echo '<img src="plugins/calendar/doc/images/calendar_icon.png" height="105" width="95" />';
	echo "</center>";
	echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
	echo '</div>';
}
?>
</div>
</div>

<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">

  <a class="btn btn-success eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> {{Sauvegarder}}</a>
  <a class="btn btn-danger eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> {{Supprimer}}</a>
  <a class="btn btn-default eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a>
  <a class="btn btn-default pull-right eqLogicAction" data-action="copy"><i class="fa fa-files-o"></i> {{Dupliquer}}</a>

  <ul class="nav nav-tabs" role="tablist">
   <li role="presentation"><a class="eqLogicAction cursor" aria-controls="home" role="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
   <li role="presentation" class="active"><a href="#generaltab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Géneral}}</a></li>
   <li role="presentation"><a id="bt_calendartab" href="#calendartab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-calendar"></i> {{Agenda}}</a></li>
 </ul>

 <div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
  <div role="tabpanel" class="tab-pane active" id="generaltab">
    <br/>
    <div class="col-sm-6">
      <form class="form-horizontal">
        <fieldset>
          <div class="form-group">
            <label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
            <div class="col-sm-4">
              <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
              <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement gCalendar}}"/>
            </div>
          </div>
          <div class="form-group">
            <label class="col-sm-4 control-label" >{{Objet parent}}</label>
            <div class="col-sm-4">
              <select class="eqLogicAttr form-control" data-l1key="object_id">
                <option value="">{{Aucun}}</option>
                <?php
foreach (object::all() as $object) {
	echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
}
?>
             </select>
           </div>
         </div>
         <div class="form-group">
          <label class="col-sm-4 control-label">{{Catégorie}}</label>
          <div class="col-sm-8">
            <?php
foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
	echo '<label class="checkbox-inline">';
	echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
	echo '</label>';
}
?>
         </div>
       </div>
       <div class="form-group">
        <label class="col-sm-4 control-label"></label>
        <div class="col-sm-8">
          <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>{{Activer}}</label>
          <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>{{Visible}}</label>
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-4 control-label">{{Widget, nombre de jours}}</label>
        <div class="col-sm-2">
          <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="nbWidgetDay" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-4 control-label">{{Nombre d'événement maximum}}</label>
        <div class="col-sm-2">
          <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="nbWidgetMaxEvent" />
        </div>
      </div>
      <div class="form-group">
        <label class="col-sm-4 control-label">{{Masquer le statut et les commandes d'activation/désactivation}}</label>
        <div class="col-sm-8">
          <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="noStateDisplay" checked/></label>
        </div>
      </div>

    </fieldset>
  </form>
</div>
<div class="col-sm-6">
  <form class="form-horizontal">
    <fieldset>
      <legend><i class="fa fa-list"></i>  {{Liste des événements de l'agenda}}</legend>
      <div id="div_eventList"></div>
      <br/>
      <div class="form-group">
        <div class="col-sm-6">
          <a class="btn btn-default" id="bt_addEvent"><i class="fa fa-plus-circle"></i> {{Ajouter événement}}</a>
        </div>
      </div>
    </fieldset>
  </form>
</div>
</div>

<div role="tabpanel" class="tab-pane" id="calendartab">
  <br/>
  <div id="div_calendar"></div>
</div>
</div>

<?php
include_file('3rdparty', 'fullcalendar/lib/moment.min', 'js', 'calendar');
include_file('3rdparty', 'datetimepicker/jquery.datetimepicker', 'js', 'calendar');
include_file('3rdparty', 'fullcalendar/fullcalendar.min', 'js', 'calendar');
include_file('3rdparty', 'fullcalendar/locale/fr', 'js', 'calendar');
include_file('desktop', 'calendar', 'js', 'calendar');
include_file('core', 'plugin.template', 'js');
?>