/**
 * WPUF Block
 *
 * A block for embedding a wpuf form into a post/page.
 */
( function( blocks, i18n, editor, element, components ) {

    var el = element.createElement, // function to create elements
        TextControl = components.TextControl,// text input control
        InspectorControls = editor.InspectorControls, // sidebar controls
        InnerBlocks = editor.InnerBlocks;

    // register our block
    blocks.registerBlockType( 'wpuf/form', {
        title: 'WPUF Forms',
        icon: 'feedback',
        category: 'common',

        attributes: {
            formID: {
                type: 'integer',
                default: 0
            },
            formName: {
                type: 'string',
                default: ''
            }
        },

        edit: function( props ) {

            var formID = props.attributes.formID;

            var formName = props.attributes.formName;

            var children = [];

            if( ! formID ) formID = ''; // Default.
            if( ! formName ) formName = ''; // Default

            // this function is required, but we don't need it to do anything
            function wpufOnValueChange( formName ) { }

            // show the dropdown when we click on the input
            function wpufFocusClick( event ) {
                var elID = event.target.getAttribute( 'id' );
                var idArray = elID.split( '-' );
                var wpufOptions = document.getElementById( 'wpuf-filter-container-' + idArray[ idArray.length -1 ] );
                // get the related input element
                var wpufInput = document.getElementById( 'wpuf-formFilter-' + idArray[ idArray.length -1 ] );
                // set focus to the element so the onBlur function runs properly
                wpufInput.focus();
                wpufOptions.style.display = 'block';
            }

            // function for select the form on filter drop down item click
            function selectForm( event ) {
                //set the attributes from the selected for item
                props.setAttributes( {
                    formID: parseInt( event.target.getAttribute( 'data-formid' ) ),
                    formName: event.target.innerText
                } );
                /**
                 * Get the main div of the filter to tell if this is being
                 * selected from the sidebar or block so we can hide the dropdown
                 */
                var elID = event.target.parentNode.parentNode;
                var idArray = elID.getAttribute( 'id' ).split( '-' );
                var wpufOptions = document.getElementById( 'wpuf-filter-container-' + idArray[ idArray.length -1 ] );
                var inputEl = document.getElementById( 'wpuf-formFilter-sidebar' );

                if( inputEl ) {
                    inputEl.value = '';
                }
                wpufOptions.style.display = 'none';
            }

            function wpufHideOptions( event ) {
                /**
                 * Get the main div of the filter to tell if this is being
                 * selected from the sidebar or block so we can hide the dropdown
                 */
                var elID = event.target.getAttribute( 'id' );
                var idArray = elID.split( '-' );
                var wpufOptions = document.getElementById( 'wpuf-filter-container-' + idArray[ idArray.length -1 ] );
                wpufOptions.style.display = 'none';
            }

            function wpufInputKeyUp( event ) {
                var val = event.target.value;
                /**
                 * Get the main div of the filter to tell if this is being
                 * selected from the sidebar or block so we can SHOW the dropdown
                 */
                var filterInputContainer = event.target.parentNode.parentNode.parentNode;
                filterInputContainer.querySelector( '.wpuf-filter-option-container' ).style.display = 'block';
                filterInputContainer.style.display = 'block';

                // Let's filter the forms here
                _.each( wpufBlock.forms, function( form, index ) {
                    var liEl = filterInputContainer.querySelector( "[data-formid='" + form.value + "']" );
                    if ( 0 <= form.label.toLowerCase().indexOf( val.toLowerCase() ) ) {
                        // shows options that DO contain the text entered
                        liEl.style.display = 'block';
                    } else {
                        // hides options the do not contain the text entered
                        liEl.style.display = 'none';
                    }
                });
            }

            // Set up the form items from the localized php variables
            var formItems = [];
            _.each( wpufBlock.forms, function( form, index ) {
                formItems.push( el( 'li', { className: 'wpuf-filter-option',
                        'data-formid': form.value, onMouseDown: selectForm},
                        form.label + " ( ID: " + form.value + " )" ))
            });

            // Set up form filter for the block
            var inputFilterMain = el( 'div', { id: 'wpuf-filter-input-main',
                    className: 'wpuf-filter-input' },
                el( TextControl, { id: 'wpuf-formFilter-main',
                    placeHolder: 'Select a Form',
                    className: 'wpuf-filter-input-el blocks-select-control__input',
                    onChange: wpufOnValueChange,
                    onClick: wpufFocusClick,
                    onKeyUp: wpufInputKeyUp,
                    onBlur: wpufHideOptions
                } ),
                el( 'span', { id: 'wpuf-filter-input-icon-main',
                    className: 'wpuf-filter-input-icon',
                    onClick: wpufFocusClick,
                    dangerouslySetInnerHTML: { __html: '&#9662;' } } ),
                el( 'div', { id: 'wpuf-filter-container-main',
                        className: 'wpuf-filter-option-container' },
                        el( 'ul', null, formItems )
                )
            );
            // Create filter input for the sidebar blocks settings
            var inputFilterSidebar = el( 'div', { id: 'wpuf-filter-input-sidebar',
                    className: 'wpuf-filter-input' },
                el( TextControl, { id: 'wpuf-formFilter-sidebar',
                    placeHolder: 'Select a Form',
                    className: 'wpuf-filter-input-el blocks-select-control__input',
                    onChange: wpufOnValueChange,
                    onClick: wpufFocusClick,
                    onKeyUp: wpufInputKeyUp,
                    onBlur: wpufHideOptions
                } ),
                el( 'span', { id: 'wpuf-filter-input-icon-sidebar',
                    className: 'wpuf-filter-input-icon',
                    onClick: wpufFocusClick,
                    dangerouslySetInnerHTML: { __html: '&#9662;' } } ),
                el( 'div', { id: 'wpuf-filter-container-sidebar',
                        className: 'wpuf-filter-option-container' },
                    el( 'ul', null, formItems )
                )
            );

            // Set up the form filter dropdown in the side bar 'block' settings
            var inspectorControls = el( InspectorControls, {},
                el( 'span', null, 'Current selected form:' ),
                el( 'br', null ),
                el( 'span', null, formName ),
                el( 'br', null ),
                el ('hr', null ),
                el ( 'label', { for: 'wpuf-formFilter-sidebar' }, 'Type to' +
                    ' filter' +
                    ' forms' ),
                inputFilterSidebar
            );

            /**
             * Create the div container, add an overlay so the user can interact
             * with the form in Gutenberg, then render the iframe with form
             */
            if( '' === formID ) {
                children.push( el( 'div', {style : {width: '100%'}},
                    el( 'img', { className: 'wpuf-block-logo', src: wpufBlock.block_logo}),
                    el ( 'div', null, 'WPUF Forms'),
                    inputFilterMain
                ) );
            } else {
                children.push(
                    el( 'div', { className: 'wpuf-iframe-container' },
                        el( 'div', { className: 'wpuf-iframe-overlay' } ),
                        el( 'iframe', { src: wpufBlock.siteUrl + '?wpuf_preview=1&wpuf_iframe&form_id=' + formID, height: '0', width: '500', scrolling: 'no' })
                    )
                )
            }
            children.push(inspectorControls);
            return [
                children
            ];
        },

        save: function( props ) {
            var formID = props.attributes.formID;

            if( ! formID ) return '';
            /**
             * we're essentially just adding a short code, here is where
             * it's save in the editor
             *
             * return content wrapped in DIV b/c raw HTML is unsupported
             * going forward
             */
            var returnHTML = '[wpuf_form id=' + parseInt( formID ) + ']';
            return el( 'div', null, returnHTML );
        }
    } );

    // WPUF Content Restriction Block
    blocks.registerBlockType( 'wpuf/content-restriction', {
        title: 'WPUF Content Restriction',
        icon: 'universal-access-alt',
        category: 'common',
 
        attributes: {
            roles: {
                type: 'array',
                default: [' '],
            },
            subscriptions: {
                type: 'array',
                default: [0],
            },
        },

        edit: function( props ) {
            
            const blcokIcon = el('span', {
                className: 'dashicons dashicons-hidden'   
            }, '')

            const blockTitle = el('p', {
                className: 'wpuf-content-restriction-title'
            }, [ blcokIcon, 'WPUF Content Restrictions' ])

            const blocSubkTitle = el('p', {
                className: 'wpuf-content-restriction-sub-title'
            }, 'Blocks within the Content Restriction block will restricted to viewers with role and subscription pack')

            // Set up the user role settings
            var availableRoles = [];
            var newRoles = props.attributes.roles.slice();

            _.each( wpufBlock.roles, function ( name, key ) {
                availableRoles.push(
                    el( components.CheckboxControl, {
                        key: key,
                        label: name,
                        value: key,
                        onChange: function( value ) {
                            if ( newRoles.indexOf(key) == -1 ) {
                                newRoles.push( key );
                            } else {
                                newRoles.splice(newRoles.indexOf(key), 1)
                            }
                            props.setAttributes({ roles: newRoles });
                        },
                        checked: props.attributes.roles.indexOf(key) != -1 || 'administrator' === key ? true : false
                    })
                )
            });
            
            var roles = el( InspectorControls, {},
                el(
                    components.PanelBody, {
                        title: 'Roles'
                    },
                    availableRoles
                ),
            );

            // Setup subscriptions settings
            var availableSubscriptions = [];
            var newSubscriptions = props.attributes.subscriptions.slice()

            wpufBlock.subscriptions.forEach( subscription => {
                availableSubscriptions.push(
                    el( components.CheckboxControl, {
                        key: subscription.ID,
                        label: subscription.post_title,
                        onChange: function( value ) {
                            if ( newSubscriptions.indexOf(subscription.ID) == -1 ) {
                                newSubscriptions.push( subscription.ID );
                            } else {
                                newSubscriptions.splice(newSubscriptions.indexOf(subscription.ID), 1)
                            }
                            props.setAttributes({ subscriptions: newSubscriptions });
                        },
                        checked: props.attributes.subscriptions.indexOf(subscription.ID) != -1 ? true : false
                    })
                )
            });

            var subscriptions = el( InspectorControls, {},
                el(
                    components.PanelBody, {
                        'title': 'Subscription',
                    },
                    availableSubscriptions
                )    
            )

            return [roles, subscriptions, el(
                'div',
                { className: 'wpuf-content-restriction-block' },
               [ blockTitle, blocSubkTitle, el( InnerBlocks ) ]
            )];
        },
 
        save: function( props ) {
            var subscription = props.attributes.subscriptions
            var roles = props.attributes.roles

            return el(
                'div',
                { className: props.className },
                
                ['[wpuf_content_restrict roles="'+ roles +'" subscriptions="'+ subscription.toString() +'"]', el( InnerBlocks.Content ), '[/wpuf_content_restrict]']
            );
        },
    } );


} )(
    window.wp.blocks,
    window.wp.i18n,
    window.wp.editor,
    window.wp.element,
    window.wp.components
);