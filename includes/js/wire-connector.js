/*
 *  This Javascript File will be used to dynamically create WP Options for the Goal Page.
 *  Things to keep in mind is the shortcodes.
 *
 */

function add_new_goal(event){
    event.preventDefault();

    create_label();

    create_input();

    create_break();
}

function button_disabled(status){
    var button = document.getElementById('add_goal');
    button.disabled = status;
}

function create_label(){
    var goal_section = document.getElementById('goal_wc_page');
    var label = document.createElement('label');
    label.textContent = 'Label for goals';
    label.setAttribute('class', 'goal goal-label');
    goal_section.appendChild(label);
}

function create_input(){
    var goal_section = document.getElementById('goal_wc_page');
    var input = document.createElement('input');
    input.setAttribute( 'type', 'text');
    input.setAttribute('class', 'goal goal-input');
    goal_section.appendChild(input);
}

function create_break(){
    var goal_section = document.getElementById('goal_wc_page');
    var break_tag = document.createElement('br');
    goal_section.appendChild(break_tag);
}
