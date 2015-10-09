	pimcore.registerNS("pimcore.plugin.promoteobjects");

	pimcore.plugin.promoteobjects = Class.create(pimcore.plugin.admin, {

		//this.oobj = null;
	    getClassName: function() {
	        return "pimcore.plugin.promoteobjects";
	    },

	    initialize: function() {
	        pimcore.plugin.broker.registerPlugin(this);
	    },
	 
	    pimcoreReady: function (params,broker){

	    	//Object Change(in no promote List) button
	    	var action = new Ext.Action({
                id: "promote_exceptions_menu",
                text: "Promote List Exceptions",
                iconCls:"pimcore_icon_stop",
                handler: this.showPromoteExceptionsTab
            });
            layoutToolbar.marketingMenu.add(action);


	        var tabpanel = Ext.getCmp("pimcore_panel_tabs");
			tabpanel.on('tabchange',function (tabPanel, tab) {
				if(tab != undefined && tab.object != undefined && tab.object.data.general.o_className == 'PromoteList'){
					promoteobjectsPlugin.object = tab.object;
					var id = promoteobjectsPlugin.id;
				} else if(tab != undefined && tab.object != undefined){
					promoteobjectsPlugin.oobject = tab.object;
				}
	            
	        });
	    },
	  
	    onLayoutResize: function (el, width, height, rWidth, rHeight) {
	        this.setLayoutFrameDimensions(width, height);
	    },
	    setLayoutFrameDimensions: function (width, height) {
	        Ext.get("object_iframe_" + this.object.id).setStyle({
	            width: width + "px",
	            height: (height - 25) + "px"
	        });
	    },
	    onRowClick: function (grid, rowIndex, event) {
	            this.showObjectPreview(grid, rowIndex, event);
	    },
	    showObjectPreview: function (grid, rowIndex, event) {
	        var data = grid.getStore().getAt(rowIndex).data;
	        var objkey = data.key;
	        var objid = this.object.id;
	        var path = "/plugin/PromoteObjects/index/preview-version/id/"+objid+"/?key="+objkey;
	        Ext.get("object_iframe_" + this.object.id).dom.src = path;
	    },
	    preOpenObject : function(object){
	    	/*
	    	* Method to disable delete rows from right click context menu of PromoteList Object Relation
	    	* Author: Divesh Pahuja, OSSC
	    	* Date: 17-02-2015
	    	*/ 
	    	Ext.override(pimcore.object.tags.objects, {
	        	   onRowContextmenu: function (grid, rowIndex, event) {
			        //Ext.get(grid.getView().getRow(rowIndex)).frame();
			        //grid.getSelectionModel().selectRow(rowIndex);

			        var menu = new Ext.menu.Menu();
			        var data = grid.getStore().getAt(rowIndex);

			        /*menu.add(new Ext.menu.Item({
			            text: t('remove'),
			            iconCls: "pimcore_icon_delete",
			            handler: this.reference.removeObject.bind(this, rowIndex)
			        }));*/

			        menu.add(new Ext.menu.Item({
			            text: t('open'),
			            iconCls: "pimcore_icon_open",
			            handler: function (data, item) {
			                item.parentMenu.destroy();
			                pimcore.helpers.openObject(data.data.id, "object");
			            }.bind(this, data)
			        }));

			        menu.add(new Ext.menu.Item({
			            text: t('search'),
			            iconCls: "pimcore_icon_search",
			            handler: function (item) {
			                item.parentMenu.destroy();
			                this.openSearchEditor();
			            }.bind(this.reference)
			        }));

			        event.stopEvent();
			        menu.showAt(event.getXY());
			    }
	        });

	    	Ext.override(pimcore.object.tags.objects, {
	            getLayoutEdit: function () {
			        var autoHeight = false;
			        if (intval(this.fieldConfig.height) < 15) {
			            autoHeight = true;
			        }

			        var cls = 'object_field';

			        this.component = new Ext.grid.GridPanel({
			            store: this.store,
			            enableDragDrop: true,
			            ddGroup: 'element',
			            sm: new Ext.grid.RowSelectionModel({singleSelect:true}),
			            colModel: new Ext.grid.ColumnModel({
			                defaults: {
			                    sortable: false
			                },
			                columns: [
			                    {header: 'ID', dataIndex: 'id', width: 50},
			                    {id: "path", header: t("path"), dataIndex: 'path', width: 200},
			                    {header: t("type"), dataIndex: 'type', width: 100},
			                    {
			                        xtype:'actioncolumn',
			                        width:30,
			                        items:[
			                            {
			                                tooltip:t('up'),
			                                icon:"/pimcore/static/img/icon/arrow_up.png",
			                                handler:function (grid, rowIndex) {
			                                    if (rowIndex > 0) {
			                                        var rec = grid.getStore().getAt(rowIndex);
			                                        grid.getStore().removeAt(rowIndex);
			                                        grid.getStore().insert(rowIndex - 1, [rec]);
			                                    }
			                                }.bind(this)
			                            }
			                        ]
			                    },
			                    {
			                        xtype:'actioncolumn',
			                        width:30,
			                        items:[
			                            {
			                                tooltip:t('down'),
			                                icon:"/pimcore/static/img/icon/arrow_down.png",
			                                handler:function (grid, rowIndex) {
			                                    if (rowIndex < (grid.getStore().getCount() - 1)) {
			                                        var rec = grid.getStore().getAt(rowIndex);
			                                        grid.getStore().removeAt(rowIndex);
			                                        grid.getStore().insert(rowIndex + 1, [rec]);
			                                    }
			                                }.bind(this)
			                            }
			                        ]
			                    },
			                    {
			                        xtype: 'actioncolumn',
			                        width: 30,
			                        items: [
			                            {
			                                tooltip: t('open'),
			                                icon: "/pimcore/static/img/icon/pencil_go.png",
			                                handler: function (grid, rowIndex) {
			                                    var data = grid.getStore().getAt(rowIndex);
			                                    pimcore.helpers.openObject(data.data.id, "object");
			                                }.bind(this)
			                            }
			                        ]
			                    },
			                    {
			                        xtype: 'actioncolumn',
			                        width: 30,
			                        items: [
			                            {
			                                tooltip: t('remove'),
			                                icon: "/pimcore/static/img/icon/cross.png",
			                                getClass: function(v, meta, rec) {
			                                	
			                                	if(promoteobjectsPlugin.oobject.data.data){
			                                		var pObjects = promoteobjectsPlugin.oobject.data.data.promotedobjects;	
			                                	}
			                                	
			                                	if(pObjects){
			                                		var objPromoteStatus = pObjects.indexOf(rec.data.id.toString());        
	                                            	if(promoteobjectsPlugin.oobject.data.general.o_className == "PromoteList" && objPromoteStatus != -1){                                                                    
	                                                	return 'x-hide-display';
	                                            	}
			                                	}
			                                    
                                            },
			                                handler: function (grid, rowIndex) {
			                                		grid.getStore().removeAt(rowIndex);
			                                	
			                                }.bind(this)
			                            }
			                        ]
			                    }
			                ]
			            }),
			            cls: cls,
			            autoExpandColumn: 'path',
			            width: this.fieldConfig.width,
			            height: this.fieldConfig.height,
			            tbar: {
			                items: [
			                    {
			                        xtype: "tbspacer",
			                        width: 20,
			                        height: 16,
			                        cls: "pimcore_icon_droptarget"
			                    },
			                    {
			                        xtype: "tbtext",
			                        text: "<b>" + this.fieldConfig.title + "</b>"
			                    },
			                    "->",
			                    {
			                        xtype: "button",
			                        iconCls: "pimcore_icon_delete",
			                        handler: this.empty.bind(this)
			                    },
			                    {
			                        xtype: "button",
			                        iconCls: "pimcore_icon_search",
			                        handler: this.openSearchEditor.bind(this)
			                    },
			                    this.getCreateControl()
			                ],
			                ctCls: "pimcore_force_auto_width",
			                cls: "pimcore_force_auto_width"
			            },
			            autoHeight: autoHeight,
			            bodyCssClass: "pimcore_object_tag_objects"
			        });
			        this.component.on("rowcontextmenu", this.onRowContextmenu);
			        this.component.reference = this;

			        this.component.on("afterrender", function () {

			            var dropTargetEl = this.component.getEl();
			            var gridDropTarget = new Ext.dd.DropZone(dropTargetEl, {
			                ddGroup    : 'element',
			                getTargetFromEvent: function(e) {
			                    return this.component.getEl().dom;
			                    //return e.getTarget(this.grid.getView().rowSelector);
			                }.bind(this),
			                onNodeOver: function (overHtmlNode, ddSource, e, data) {
			                    if (this.dndAllowed(data)) {
			                        return Ext.dd.DropZone.prototype.dropAllowed;
			                    } else {
			                        return Ext.dd.DropZone.prototype.dropNotAllowed;
			                    }
			                }.bind(this),
			                onNodeDrop : function(target, dd, e, data) {

			                    if (this.dndAllowed(data)) {
			                        if(data["grid"] && data["grid"] == this.component) {
			                            var rowIndex = this.component.getView().findRowIndex(e.target);
			                            if(rowIndex !== false) {
			                                var rec = this.store.getAt(data.rowIndex);
			                                this.store.removeAt(data.rowIndex);
			                                this.store.insert(rowIndex, [rec]);
			                            }
			                        } else {
			                            var initData = {
			                                id: data.node.attributes.id,
			                                path: data.node.attributes.path,
			                                type: data.node.attributes.className
			                            };

			                            if (!this.objectAlreadyExists(initData.id)) {
			                                this.store.add(new this.store.recordType(initData));
			                                return true;
			                            }
			                        }
			                    }
			                    return false;
			                }.bind(this)
			            });
			        }.bind(this));


			        return this.component;
			    } 
			});
	    },
	    
	    postOpenObject : function(obj){
	    	this.oobject = obj;
	    	this.object = obj;

	    	var select = '';
		    function getClEnvironments(response) {
	            Ext.Ajax.request({
		            url: '/plugin/PromoteObjects/index/getenvironment',
		            async:false,
		            params: {},
		            method: 'POST',
		            success: function(data) {
		                  response(data); 
		            },
	          	});
		    }
		    
		    

			//Add Promote List button on object open, if object type is PromoteList
		    if(obj.data.general.o_className == "PromoteList"){
		    	getClEnvironments(function(response){
		    		var currentEnv = Ext.decode(response.responseText).data.envCurrent;
		    			if(currentEnv != 'production' && currentEnv != 'Production'){
				    		obj.toolbar.insert(2, {
					        text: 	 'Promote',
					        itemId:  'promote_object',
					        scale: 	 'medium',
					        iconCls: 'pimcore_icon_upload_medium',
					        handler: function(button) {
					        	var publishState = Ext.decode(obj.getSaveData().general).o_published;

					        	if(this.isDirty() || publishState == false){
					        		Ext.MessageBox.show({
			                            title: 'Error',
			                            msg: "Please <b>Save & Publish</b> promote list.",
			                            buttons: Ext.MessageBox.OK,
			                            icon: Ext.MessageBox.ERROR,
			                            minWidth: 300,
			                        });
					        	} else if(obj.data.data.promoted == null && obj.data.data.objects == false  && obj.getSaveData().data == '{}' || obj.getSaveData().data == '{"objects":[]}'){
				
					        		Ext.MessageBox.show({
			                            title: 'Error',
			                            msg: "No Data to promote!",
			                            buttons: Ext.MessageBox.OK,
			                            icon: Ext.MessageBox.ERROR,
			                            minWidth: 300,
			                        });
					        	} 
					        	else{
					        		getClEnvironments(function(response){
					           		var res 	 = Ext.decode(response.responseText);
					           		var res 	 = res.data.envVars;
					                select = '<br /><br /><select id="envselect" style="margin-left:30px;text-algin:center;width:150px">';
					                if(res == null){
					                	Ext.MessageBox.show({
			                                title: 'Promote List',
										    msg: 'Please select environment to promote list'+select,
									    	buttons: Ext.MessageBox.OKCANCEL,
			                                icon: Ext.MessageBox.INFO,
			                                minWidth: 300
			                        	});
					                } else {
						                Ext.each(res, function(item) {
							            	select 	+= '<option value="'+item+'">'+item+'</option>';
							            }, this);
						         	    select 		+= '</select>';

						             	Ext.MessageBox.show({
				                            title: 'Promote List',
										    msg: 'Please select environment to promote list'+select,
									    	buttons: Ext.MessageBox.OKCANCEL,
				                            icon: Ext.MessageBox.INFO,
				                            minWidth: 300,
				                            fn: function (btn) {
									        	if (btn == 'ok') {
									        		var SelectedEnv = Ext.get('envselect').getValue();
									        		Ext.MessageBox.show({
						                            title: 'Promote List',
												    msg: 'Are you sure you want to promote this list to <b>'+SelectedEnv+'</b> environment?',
											    	buttons: Ext.MessageBox.YESNO,
						                            icon: Ext.MessageBox.WARNING,
						                            minWidth: 300,
						                            fn: function (btn) {
						                            	console.log(new Date()); // Promottion Start Time
											        	if (btn == 'yes') {
											        		//Send request to promote objects
											        		Ext.Ajax.request({
												            url: '/plugin/PromoteObjects/index/index',
												            async:false,
												            params: {objectid:obj.id,environment:SelectedEnv},
												            method: 'POST',
												            success: function(data) {
												            	if(Ext.decode(data.responseText).success == true){
												            		obj.reload(obj.data.currentLayoutId);
												            		tree = pimcore.globalmanager.get("layout_object_tree").tree;
												            		tree.getRootNode().reload();
												            		console.log(new Date()); // Promottion Start Time
												            		pimcore.layout.refresh();
												            	} else{
												            			var msg = Ext.decode(data.responseText).msg;
												            			Ext.MessageBox.show({
											                                title:t('error'),
											                                msg: msg,
											                                buttons: Ext.Msg.OKCANCEL ,
											                                icon: Ext.MessageBox.ERROR
										                            	});
												            	}
												            },
												        });
												        }
												    }
												});
									        		
									            }
									        }
				                        });
									  }
			                        });
								}
								
					        }.bind(obj)
					    	});	
							if(!obj.data.data.promoted){
								obj.toolbar.insert(3,{
		    					text: 	 'Show Duplicates',
						        itemId:  'check_multiple',
						        scale: 	 'medium',
						        iconCls: 'pimcore_icon_tab_search',
						        handler: function(button) {
						        	var data = Ext.decode(obj.getSaveData().data).objects;
						        	if(data == undefined){
						        		data = obj.data.data.objects;
						        	}
						        	var dataids = [];
						        	Ext.each(data,function(obj){
						        		if(obj.id){
						        			dataids.push(obj.id);
						        		} else dataids.push(obj[0]);
						        		
						        	});

						        	data = Ext.encode(dataids);
						        	Ext.Ajax.request({
								            url: '/plugin/PromoteObjects/index/get-object-in-multiple-list',
								            async:false,
								            params: {data:data,objid:obj.id},
								            method: 'POST',
								            success: function(response) {
    											var element = obj.edit.dataFields.objects.component.body.dom;
    											var elementHeader = obj.edit.dataFields.objects.component.tbar.dom;
    											var multipleFlag = 0;
    												element = element['childNodes'][0]['childNodes'][0]['childNodes'][1]['childNodes'][0]['childNodes'];
    											var responseElements = Ext.decode(response.responseText).objects;
    											Ext.each(element,function(e){
    												index = e.textContent.indexOf("/");
    												elementid = e.textContent.substring(0,index);
    												if(responseElements[elementid] > 0){
    													e.style.color = 'red';
    													multipleFlag = 1;
    												} else e.style.color = 'black';
    											});
    											if(multipleFlag == 1){
    												elementHeader.style.color = 'red';
    											} else elementHeader.style.color = 'black';
								            },
							         });
						        		 }.bind(obj)
		    				});
							pimcore.layout.refresh();
							}
							
						pimcore.layout.refresh();
		    		}

		    });
		    	//hide delete button from object relations in PromoteList
		    	if(obj.data.general.o_className == "PromoteList"){
			    	var dd = Ext.get('object_' + obj.id).query(".x-toolbar-right-row");
		    		dd[1].childNodes[0].hidden = true;
		    	}

		    	//Get the buttons
				buttons = obj.toolbar.items.items;
				for (var i in buttons) {
					var buttontext = buttons[i].text;
					if (buttontext=="Save & Publish" || buttontext=="Unpublish" || buttontext=="Delete"){} 
				//	buttons[i].hide();
				}
				//promote list button with Promote handler

		    } else{

		    	function getClObject(response) {
	            Ext.Ajax.request({
		            url: '/plugin/PromoteObjects/index/get-pl-object',
		            async:false,
		            params: {id:obj.id},
		            method: 'POST',
		            success: function(data) {
		                  response(data); 
		            },
	          	});
		    }

		    getClObject(function(response){
		    	var res 	  = Ext.decode(response.responseText);
		    	var plObjects = res.data;
		    	
		    	if(plObjects === null || plObjects.length === 0){
		    		obj.toolbar.addSeparator(1);
	     	 		obj.toolbar.addText("<b>No Promote List</b>");
		    	} else if(plObjects.length == 1){
		    		obj.toolbar.addSeparator(1);
	     	 		obj.toolbar.addText("<b>PL: "+plObjects[0]+"</b>");
		    	} else{
		    		obj.toolbar.addSeparator(1);
	     	 		obj.toolbar.addText("<b>PL: </b>");

	     	 		var multiMenu = new Ext.menu.Menu({
	     	 						width: 150,
									items: []
									});

	     	 		Ext.each(plObjects, function(item){
                             multiMenu.addItem('List: '+item);
                    });
	     	 		var multiButton = new Ext.SplitButton({
											    text: 'Multilple',
											    menu:multiMenu 
											});
			      //obj.toolbar.addSeparator(1);
			      //obj.toolbar.add('->');
			      obj.toolbar.addButton(multiButton);
		    	}
		    	
		    	pimcore.layout.refresh();
		    });
		   

		    }

		if(obj.data.general.o_className == "PromoteList" && obj.data.data.promoted){
			//promote list button with Promote handler
			/*	obj.toolbar.insert(3, {
			        text: 	 'Rollback Promotion',
			        itemId:  'rollback_promote_object',
			        scale: 	 'medium',
			        iconCls: 'pimcore_icon_download_medium',
			        handler: function(button) {
			        	Ext.MessageBox.show({
		                            title: 'Rollback Promote List',
								    msg: 'Are You Sure want to Rollback Promote List',
							    	buttons: Ext.MessageBox.YESNO,
		                            icon: Ext.MessageBox.INFO,
		                            minWidth: 300,
		                            fn: function (btn) {
							        	if (btn == 'yes') {
							        			Ext.Ajax.request({
										            url: '/plugin/PromoteObjects/index/roll-back-promoted-object',
										            async:false,
										            params: {objectid:obj.id,environment:"Test"},
										            method: 'POST',
										            success: function(data) {
										            	if(Ext.decode(data.responseText).success == true){
										            		obj.reload(obj.data.currentLayoutId);
										            		tree = pimcore.globalmanager.get("layout_object_tree").tree;
										            		tree.getRootNode().reload();
										            		pimcore.layout.refresh();
										            	}
										            },
										        });	
							            }
							        }
		                        });
						
			        }.bind(obj)
			    });*/
	getClEnvironments(function(response){
	var currentEnv = Ext.decode(response.responseText).data.envCurrent;
	var plOwner    = Ext.decode(response.responseText).data.plOwner;
	var reOpen     = Ext.decode(response.responseText).data.reOpenList; 

	if(reOpen == 'Y' || reOpen == 'y' && plOwner == 1){
				obj.toolbar.insert(3, {
			        text: 	 'Reopen Promote List',
			        itemId:  'rollback_promote_object',
			        scale: 	 'medium',
			        iconCls: 'pimcore_icon_download_medium',
			        handler: function(button) {
			        	Ext.MessageBox.show({
		                            title: 'Reopen Promote List',
								    msg: 'Are you sure you want to Reopen Promote List?',
							    	buttons: Ext.MessageBox.YESNO,
		                            icon: Ext.MessageBox.WARNING,
		                            minWidth: 300,
		                            fn: function (btn) {
							        	if (btn == 'yes') {
							        			Ext.Ajax.request({
										            url: '/plugin/PromoteObjects/index/re-open-promote-list',
										            async:false,
										            params: {oPid:obj.id},
										            method: 'POST',
										            success: function(data) {
										            	if(Ext.decode(data.responseText).success == true){
										            		obj.reload(obj.data.currentLayoutId);
										            		tree = pimcore.globalmanager.get("layout_object_tree").tree;
										            		tree.getRootNode().reload();
										            		pimcore.layout.refresh();
										            	}
										            },
										        });	
							            }
							        }
		                        });
						
			        }.bind(obj)
			    });
	pimcore.layout.refresh();
}
});
				console.log(obj.data.data);
				var pEnvironemt =	(obj.data.data.environment?obj.data.data.environment:'None');
				var pDate		=	(obj.data.data.promotionDate?new Date(obj.data.data.promotionDate * 1000).format("Y-m-d H:i:s"):'None');
				var pUser 		=	(obj.data.data.plUser?obj.data.data.plUser:'None');

				if (this.layout == null) {
		            this.store = new Ext.data.JsonStore({
		                autoDestroy: true,
		                url: "/plugin/PromoteObjects/index/get-view-json",
		                baseParams: {
		                    id: this.object.id
		                },
		                root: 'cl_objects',
		                sortInfo: {
		                    field: 'key',
		                    direction: 'ASC'
		                },
		                fields: ['key']
		            });

		            var objgrid = new Ext.grid.GridPanel({
		                store: this.store,
		                columns: [{id:"rownum",width:35,header: "s.no",renderer: function(value, metaData, record, rowIndex, colIndex, store) { return rowIndex+1;}},
		                    {id: "object", header: "Objects", sortable: true, dataIndex: 'key'},
		                    {id: "status", header: "",width:30, sortable: true, dataIndex: 'status',  renderer: function(value, metaData, record, rowIndex, colIndex, store) {
							     if(record.json.status == 'success'){
							      	return "<div style='height:15px; margin:0 auto;' class='icon_notification_success'></div>";
							      } else {
							      	return "<div style='height:15px; margin:0 auto;' class='promoteObjects_icon_warning'></div>";
							      }
							   }
		                    },
		                ],
		                stripeRows: true,
		                width:300,
		                minSize: 300,
	               		maxSize: 600,
		                animate:true,
	                	containerScroll: true,
	                	border: true,
	                	split:true,
	                	layout:'fit',
	                	autoExpandColumn: 'object',
		                title: 'Promoted to Environment: '+pEnvironemt+'<br> Date: '+pDate+'<br> User: '+pUser,
		                region: "west",
		                viewConfig: {
		                    getRowClass: function(record, rowIndex, rp, ds) {
		                        if (record.data.date == this.object.data.general.o_modificationDate) {
		                            return "version_published";
		                        }
		                        return "";
		                    }.bind(this)
		                }
		            });

		            objgrid.on("rowclick", this.onRowClick.bind(this));
		            //grid.on("rowcontextmenu", this.onRowContextmenu.bind(this));
		            objgrid.on("beforerender", function () {
		                this.store.load();
		            }.bind(this));

		            objgrid.reference = this;

		            var objpreview = new Ext.Panel({
		                title: 'Object Preview',
		                region: "center",
		                bodyStyle: "-webkit-overflow-scrolling:touch;",
		                html: '<iframe src="about:blank" frameborder="0" id="object_iframe_' + this.object.id
		                                                                + '"></iframe>'
		            });

		            objpreview.on("resize", this.onLayoutResize.bind(this));
	        }
	       
	        obj.tabbar.insert(0,{ title: 'View',
	        	title: t('view'),
		        bodyStyle:'padding:20px 5px 20px 5px;',
		        layout: "border",
		        iconCls: "pimcore_icon_tab_view",
		        items: [objgrid,objpreview]
		    });
	        //hide Edit tab and Activate View Tab
	        obj.tabbar.items.items[1].hide();
	        obj.tabbar.hideTabStripItem(1);
	        obj.tabbar.activate(0);
	        buttons = obj.toolbar.items.items;
				for (var i in buttons) {
					var buttontext = buttons[i].text;
					if (buttontext=="Save & Publish" || buttontext=="Unpublish" || buttontext=="Save" || buttontext=="Delete"){
					buttons[i].hide();
					}
				}
			}
		},

		showPromoteExceptionsTab: function() {
                promoteobjectsPlugin.exceptionPanel = new Ext.Panel({
                    id:         "promote_exceptions_panel",
                    title:      "Promote List Exceptions Report",
                    width: 700,
                    height: 500,
                    layout:     "border",
                    closable:   true,
                    items:      [
                        {
                            title: 'Search Parameters',
                            region: 'west',     // position for region
                            width:150,
                            items:[promoteobjectsPlugin.getExceptionFormPanel()]
                        },
                        {
                            title: 'Data',
                            region: 'center',     // position for region
                            layout:"fit",
                            items:[promoteobjectsPlugin.getExceptionGrid()]
                        }
                    ],
                });
                
                var tabPanel = Ext.getCmp("pimcore_panel_tabs");
                tabPanel.add(promoteobjectsPlugin.exceptionPanel);
                tabPanel.activate("promote_exceptions_panel");
         
                pimcore.layout.refresh();

            return promoteobjectsPlugin.exceptionPanel;
        },

        getExceptionFormPanel: function (){

        	var modifiedByStore =  new Ext.data.JsonStore({
			    autoDestroy: false,
                url: '/plugin/PromoteObjects/utility/get-users',
                root: 'users',
                fields: ['id','name']
			});
			var classTypeStore =  new Ext.data.JsonStore({
			    autoDestroy: false,
                url: '/plugin/PromoteObjects/utility/get-classes',
                root: 'classType',
                fields: ['id','name']
			});
			 modifiedByStore.load();
			 classTypeStore.load();

        	// create the combo instance
		promoteobjectsPlugin.formPanel = new Ext.form.FormPanel({
                id: "myformpanel",
                width: 150,
                bodyStyle: "padding:5px",
                labelAlign: "top",
                defaults:
                {
                        anchor: "100%"
                },
              items:[{
                	 //Date Range Field set
			        xtype:'fieldset',
			        columnWidth: 150,
			        title: 'Date Range',
			        collapsible: false,
			        defaultType: 'datefield',
			        items :[{
                    		id:"fromDate",
                            name: "fromDate",
                            fieldLabel: "From Date",
                             allowBlank:false
                    },
                    {
                    		id:"toDate",
                            name: "toDate",
                            fieldLabel: "To Date",
                             allowBlank:false
                    }]
                },
				{
				  xtype: 'combo', 
				  fieldLabel: "Modified By",
				  id : 'modifiedBy', 
				  name : 'modifiedBy', 
				  store: modifiedByStore,
				  valueField: 'id',
 				  displayField: 'name',
				  mode: 'local',
				  value: 'All',
				  triggerAction: 'all',
				  editable : false,
				  listeners: {
				     select: function(val) {
				     }
				 }
				},
				{
				  xtype: 'combo', 
				  fieldLabel: "Object Type",
				  id : 'classType', 
				  name : 'classType', 
				  store:classTypeStore,
				  valueField: 'id',
 				  displayField: 'name',
				  mode: 'local',
			  	  value: 'All',
				  triggerAction: 'all',
				  editable : false,
				  listeners: {
				     select: function(val) {
				     }
				 }
				},
				{
				  xtype: 'combo', 
				  fieldLabel: "Published Type",
				  id : 'publishedType', 
				  name : 'publishedType', 
				  store:[["All","All"],["Published","Published"],["Unpublished","Unpublished"]],
				  mode: 'local',
				  value: 'All',
				  triggerAction: 'all',
				  editable : false,
				  listeners: {
				     select: function(val) {
				     }
				 }
				},
				],

                buttons:
                [{
                        text: "Search",
                        handler: function()
                        {
                        	var FromDate 		= Ext.getCmp('fromDate').getValue();
                        	var ToDate 			= Ext.getCmp('toDate').getValue();
                        	var ExcptToDate 	= null;
                        	var ExcptFromDate 	= null;

                        	if((!empty(FromDate) && !empty(ToDate)) && fromDate > ToDate){
        						Ext.MessageBox.show({
		                            title: 'Error',
		                            msg: "From Date must be less than End Date!",
		                            buttons: Ext.MessageBox.OK,
		                            icon: Ext.MessageBox.ERROR,
		                            minWidth: 300,
	                        	});
        					} else if(!empty(FromDate) && !empty(ToDate)){
        						ExcptFromDate = (Ext.getCmp('fromDate').getValue().getTime())/1000;
        						ExcptToDate = (Ext.getCmp('toDate').getValue().getTime())/1000;

        						promoteobjectsPlugin.store2.load();
                        		Ext.getCmp('promote_exceptions_grid').getView().refresh();
        					} else{
        						Ext.MessageBox.show({
	                            title: 'Error',
	                            msg: "From Date & End Date is Mandatory!",
	                            buttons: Ext.MessageBox.OK,
	                            icon: Ext.MessageBox.ERROR,
	                            minWidth: 300,
	                       	 });
    						}
                                // convenient way to fit the print page to the visible map area
                               // printPage.fit(true)
                        }
                }]
               
	        
        });

        return promoteobjectsPlugin.formPanel;
    },

    getExceptionGrid: function (portletId) {
    	function getPromoteLists(response) {
            Ext.Ajax.request({
	            url: '/plugin/PromoteObjects/index/get-promote-lists',
	            async:false,
	            params: {},
	            method: 'POST',
	            success: function(data) {
	                  response(data); 
	            },
          	});
	    }

            promoteobjectsPlugin.store2 = new Ext.data.JsonStore({
                autoDestroy: false,
                url: '/plugin/PromoteObjects/index/get-updated-objects',
                root: 'updatedobjects',
                fields: ['id','key','type','fullpath','modificationDate','user','published'],
                baseParam: {
                	fromDate:0,
                	toDate:0,
                	classType:'',
                	modifiedBy:'',
                	publishedType:'',
                	start:0,
                	limit:0,
                },
                listeners: {
			        beforeload: function (store) {
			            	if(Ext.getCmp('fromDate').getValue() && Ext.getCmp('toDate').getValue()){
			            		var ExcptFromDate = (Ext.getCmp('fromDate').getValue().getTime())/1000;
        						var ExcptToDate = (Ext.getCmp('toDate').getValue().getTime())/1000;
        						store.setBaseParam('fromDate',ExcptFromDate);
			                	store.setBaseParam('toDate',ExcptToDate);
			                	store.setBaseParam('classType',Ext.getCmp('classType').getValue());
			                	store.setBaseParam('modifiedBy',Ext.getCmp('modifiedBy').getValue());
			                	store.setBaseParam('publishedType',Ext.getCmp('publishedType').getValue());
			               		store.setBaseParam('limit',promoteobjectsPlugin.pagingtoolbar.pageSize);
			               		store.setBaseParam('start',0);
			            	}
			                
			            }
			        }
            });



            promoteobjectsPlugin.pagingtoolbar = new Ext.PagingToolbar({
                pageSize: 40,
                store: promoteobjectsPlugin.store2,
                displayInfo: true,
                displayMsg: '{0} - {1} /  {2}',
                emptyMsg: t("no_objects_found"),

            });

            // add per-page selection
            promoteobjectsPlugin.pagingtoolbar.add("-");

            promoteobjectsPlugin.pagingtoolbar.add(new Ext.Toolbar.TextItem({
                text: t("items_per_page")
            }));
            promoteobjectsPlugin.pagingtoolbar.add(new Ext.form.ComboBox({
                store: [
                    [10, "10"],
                    [20, "20"],
                    [40, "40"], 
                    [60, "60"],
                    [80, "80"],
                    [100, "100"]
                ],
                mode: "local",
                width: 50,
                value: 40,
                triggerAction: "all",
                listeners: {
                    select: function (box, rec, index) {
                        promoteobjectsPlugin.pagingtoolbar.pageSize = intval(rec.data.field1);
                        promoteobjectsPlugin.pagingtoolbar.moveFirst();
                    }.bind(this)
                }
            }));
            var checkBoxSelMod = new Ext.grid.CheckboxSelectionModel();
            promoteobjectsPlugin.grid = new Ext.grid.GridPanel({
                store: promoteobjectsPlugin.store2,
                selModel : checkBoxSelMod,
                id:"promote_exceptions_grid",
                region : 'center',
                tbar: [{
                        text: t("export_csv"),
                        iconCls: "pimcore_icon_export",
                        handler: function(){

                            Ext.MessageBox.show({
                                title:t('warning'),
                                msg: t('csv_object_export_warning'),
                                buttons: Ext.Msg.OKCANCEL ,
                                fn: function(btn){
                                    if (btn == 'ok'){
                                        this.startCsvExport(this.store);
                                    }
                                }.bind(this),
                                icon: Ext.MessageBox.WARNING
                            });



                        }.bind(this)
                    },
                    {
                        text: ("Add to promote-list"),
                        iconCls: "pimcore_icon_add",
                        handler: function(){
                        	getPromoteLists(function(response){
                        		
	                        	var selectedIds = [];
	                            var checkedObjectJson = promoteobjectsPlugin.grid.getSelectionModel().getSelections();
	                            if(checkedObjectJson != null){
	                            	Ext.each(checkedObjectJson, function(item){
		                            	selectedIds.push(item.id);
		                            });
		                            selectedIds = Ext.encode(selectedIds);
		                            
		                            var promoteLists = Ext.decode(response.responseText).data;
	                           // console.log(checkedObjectJson);
	                            
	                            
	                        	select = '<br /><br /><select id="promSelect" style="margin-left:30px;text-algin:center;width:150px">';
	                        	
	                        	Ext.each(promoteLists, function(item) {
					            	select 	+= '<option value="'+item.o_id+'">'+item.o_key+'</option>';
					            }, this);
				         	    select 		+= '</select>';
				         	    
				         	    
			                	Ext.MessageBox.show({
	                                title: 'Add to Promote List',
								    msg: 'Please select promote list to add objects'+select,
							    	buttons: Ext.MessageBox.OKCANCEL,
	                                icon: Ext.MessageBox.INFO,
	                                minWidth: 300,
	                                fn: function(btn){
	                                    if (btn == 'ok'){
	                                    	var selectedPl = Ext.get('promSelect').getValue();
	                                    	promoteobjectsPlugin.addToPromoteList(selectedPl,selectedIds);
	                                    	
	                                    }
	                                }.bind(this),
	                        	});
				                }
                        	});
                        }.bind(this)
                    }
                ],
                columns: [
                     checkBoxSelMod,
                    {
                        header: 'Modified Date', 
                        width: 120, 
                        sortable: true, 
                        renderer: function (d) {
                            var date = new Date(d * 1000);
                            return date.format("Y-m-d H:i:s");
                        }, 
                    dataIndex: 'modificationDate',
                    },
                    {
                        header: t('id'), 
                        id: "objid", 
                        width: 50, 
                        sortable: true, 
                        dataIndex: 'id',

                    },
                    {
                        header: t('filename'), 
                        id: "objkey", 
                        width: 130, 
                        sortable: true, 
                        dataIndex: 'key',

                    },
                    {
                        header: t('type'), 
                        id: "objtype", 
                        width: 100, 
                        sortable: true, 
                        dataIndex: 'type',

                    },
                    {
                        header: 'Full Path', 
                        id: "objfullpath", 
                        width: 70, 
                        sortable: true, 
                        dataIndex: 'fullpath',
                    },
                    {
                        header: 'Modified By', 
                        id: "userModification", 
                        width: 70, 
                        sortable: true, 
                        dataIndex: 'user',
                    },
                    {
                        header: 'Published', 
                        id: "published", 
                        width: 70, 
                        sortable: true, 
                        renderer: function (d) {
                            $published = "N";
                        	if(d==1){
                        		$published = "Y";
                            }
                            return $published;
                        }, 
                        dataIndex: 'published',
                    },
                ],
                viewConfig: {
                enableRowBody:true,
                showPreview:true,
            },
            //    plugins: plugins,
                stripeRows: true,
                autoExpandColumn: 'objfullpath',
                dataIndex: 'objid',
                bbar: promoteobjectsPlugin.pagingtoolbar,
            });

            promoteobjectsPlugin.grid.on("rowdblclick", function (grid, rowIndex, event) {
                var data = grid.getStore().getAt(rowIndex);
                pimcore.helpers.openObject(data.data.id);
            });
            
            return promoteobjectsPlugin.grid;
        },

        // Csv Export Store with filters
	    startCsvExport: function(store) {
	    	var FromDate 		= Ext.getCmp('fromDate').getValue();
        	var ToDate 			= Ext.getCmp('toDate').getValue();
        	var ExcptToDate 	= null;
        	var ExcptFromDate 	= null;

        	if((empty(FromDate) || empty(ToDate))){
				Ext.MessageBox.show({
	                title: 'Error',
	                msg: "From Date & End Date is Mandatory!",
	                buttons: Ext.MessageBox.OK,
	                icon: Ext.MessageBox.ERROR,
	                minWidth: 300,
	            });
			}
	    	var ExcptFromDate = (Ext.getCmp('fromDate').getValue().getTime())/1000;
        	var ExcptToDate = (Ext.getCmp('toDate').getValue().getTime())/1000;
	        var tzOffset = new Date().getTimezoneOffset();
	        var timezone = new Date().getTimezone();

	        var path = "/plugin/PromoteObjects/index/get-updated-objects";
	        path = path + "/?" + Ext.urlEncode({
	            timezone: timezone,
	            tzOffset: tzOffset,
	            exportstatus: 1,
	            fromDate:ExcptFromDate,
                toDate:ExcptToDate,
                classType:Ext.getCmp('classType').getValue(),
                modifiedBy:Ext.getCmp('modifiedBy').getValue(),
                publishedType:Ext.getCmp('publishedType').getValue(),

	        });
	        pimcore.helpers.download(path);
	    },
	    // Add objects to promote list
	    addToPromoteList: function(selectedPl,checkedObjectJson) {
	    	 Ext.Ajax.request({
		            url: '/plugin/PromoteObjects/index/add-to-promote',
		            async:false,
		            params: {ids:checkedObjectJson, pl: selectedPl},
		            method: 'POST',
		            success: function(response) { 
		            	 
		            	if(Ext.decode(response.responseText).success === true){

		            		Ext.MessageBox.show({
	                            title: 'Success',
	                            msg: "Selected objects successfully added to <b>"+Ext.decode(response.responseText).promoteList +"</b> promote list.",
	                            buttons: Ext.MessageBox.OK,
	                            icon: Ext.MessageBox.INFO,
	                            minWidth: 300,
	                            fn: function(btn){
                                    if (btn == 'ok'){
                                    	promoteobjectsPlugin.store2.load();
                                		Ext.getCmp('promote_exceptions_grid').getView().refresh();
                                    }
                                }.bind(this),
	                           
	                        });
		            		
		            	}
		            			                
		            },
	          });
	    },

	});

	var promoteobjectsPlugin = new pimcore.plugin.promoteobjects();

