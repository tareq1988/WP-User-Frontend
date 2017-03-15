<div class="panel-field-opt panel-field-opt-text">
    <label class="clearfix">
        {{ option_field.title }} <help-text v-if="option_field.help_text" :text="option_field.help_text"></help-text>
        <span class="pull-right">
            <input type="checkbox" v-model="show_value"> <?php _e( 'Show values', 'wpuf' ); ?>
        </span>
    </label>

    <ul :class="['option-field-option-chooser', show_value ? 'show-value' : '']">
        <li class="clearfix margin-0 header">
            <div class="selector">&nbsp;</div>

            <div class="sort-handler">&nbsp;</div>

            <div class="label">
                <?php _e( 'Label', 'wpuf' ); ?>
            </div>

            <div v-if="show_value" class="value">
                <?php _e( 'Value', 'wpuf' ) ?>
            </div>

            <div class="action-buttons">&nbsp;</div>
        </li>
    </ul>

    <ul :class="['option-field-option-chooser margin-0', show_value ? 'show-value' : '']">
        <li v-for="(option, index) in options" :key="option.id" :data-index="index" class="clearfix option-field-option">
            <div class="selector">
                <input
                    v-if="option_field.is_multiple"
                    type="checkbox"
                    :value="option.label"
                    v-model="selected"
                >
                <input
                    v-else
                    type="radio"
                    :value="option.label"
                    v-model="selected"
                    class="option-chooser-radio"
                    @click="clear_selection($event, option.label)"
                >
            </div>

            <div class="sort-handler">
                <i class="fa fa-bars"></i>
            </div>

            <div class="label">
                <input type="text" v-model="option.label" @input="set_option_label(index, option.label)">
            </div>

            <div v-if="show_value" class="value">
                <input type="text" v-model="option.value">
            </div>

            <div class="action-buttons clearfix">
                <i class="fa fa-plus-circle" @click="add_option"></i>
                <i class="fa fa-minus-circle pull-right" @click="delete_option(index)"></i>
            </div>
        </li>
    </ul>
</div>
