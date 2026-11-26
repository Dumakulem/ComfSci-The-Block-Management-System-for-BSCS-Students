<?php

return [
    'students' => [
        'title' => 'Students',
        'primaryKey' => 'studentID',
        'columns' => ['displayName' => 'Student', 'studentEmail' => 'Email', 'classBlock' => 'Block', 'isClassRep' => 'Class rep'],
        'fields' => [
            ['name' => 'studentFirstName', 'label' => 'First name', 'type' => 'text', 'required' => true, 'maxlength' => 25],
            ['name' => 'studentLastName', 'label' => 'Last name', 'type' => 'text', 'required' => true, 'maxlength' => 25],
            ['name' => 'studentMiddleName', 'label' => 'Middle name', 'type' => 'text', 'maxlength' => 25],
            ['name' => 'studentExtensionName', 'label' => 'Extension', 'type' => 'text', 'maxlength' => 10],
            ['name' => 'studentEmail', 'label' => 'Email', 'type' => 'email', 'required' => true, 'maxlength' => 100],
            ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'required' => true],
            ['name' => 'isClassRep', 'label' => 'Class representative', 'type' => 'checkbox'],
            ['name' => 'isGroupLeader', 'label' => 'Group leader', 'type' => 'checkbox'],
            ['name' => 'blockID', 'label' => 'Block', 'type' => 'select', 'required' => true, 'options' => 'blocks'],
        ],
    ],
    'blocks' => [
        'title' => 'Blocks',
        'primaryKey' => 'blockID',
        'columns' => ['classBlock' => 'Block', 'courseName' => 'Course', 'facultyName' => 'Faculty'],
        'fields' => [
            ['name' => 'classBlock', 'label' => 'Block name', 'type' => 'text', 'required' => true, 'maxlength' => 30],
            ['name' => 'courseID', 'label' => 'Course', 'type' => 'select', 'required' => true, 'options' => 'courses'],
            ['name' => 'facultyID', 'label' => 'Faculty', 'type' => 'select', 'options' => 'faculty'],
        ],
    ],
    'assignments' => [
        'title' => 'Assignments',
        'primaryKey' => 'assignmentID',
        'columns' => ['assignmentTitle' => 'Assignment', 'assignmentSubject' => 'Subject', 'classBlock' => 'Block', 'assignmentDeadline' => 'Deadline', 'assignmentStatus' => 'Status'],
        'fields' => [
            ['name' => 'blockID', 'label' => 'Block', 'type' => 'select', 'required' => true, 'options' => 'blocks'],
            ['name' => 'assignmentTitle', 'label' => 'Title', 'type' => 'text', 'required' => true, 'maxlength' => 150],
            ['name' => 'assignmentSubject', 'label' => 'Subject', 'type' => 'text', 'required' => true, 'maxlength' => 150],
            ['name' => 'assignmentDescription', 'label' => 'Description', 'type' => 'textarea', 'full' => true],
            ['name' => 'assignmentFilePath', 'label' => 'File path', 'type' => 'text', 'maxlength' => 255],
            ['name' => 'assignmentStatus', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['Active', 'Completed', 'Cancelled']],
            ['name' => 'assignmentDeadline', 'label' => 'Deadline', 'type' => 'datetime-local', 'required' => true],
        ],
    ],
    'events' => [
        'title' => 'Events',
        'primaryKey' => 'eventID',
        'columns' => ['eventTitle' => 'Event', 'eventDate' => 'Date', 'eventStartTime' => 'Starts', 'eventLocation' => 'Location', 'eventStatus' => 'Status'],
        'fields' => [
            ['name' => 'eventTitle', 'label' => 'Title', 'type' => 'text', 'required' => true, 'maxlength' => 150],
            ['name' => 'eventDescription', 'label' => 'Description', 'type' => 'textarea', 'full' => true],
            ['name' => 'eventDate', 'label' => 'Date', 'type' => 'date', 'required' => true],
            ['name' => 'eventStartTime', 'label' => 'Start time', 'type' => 'time', 'required' => true],
            ['name' => 'eventEndTime', 'label' => 'End time', 'type' => 'time', 'required' => true],
            ['name' => 'eventLocation', 'label' => 'Location', 'type' => 'text', 'required' => true, 'maxlength' => 150],
            ['name' => 'participationFee', 'label' => 'Participation fee', 'type' => 'number', 'required' => true, 'step' => '0.01', 'min' => '0'],
            ['name' => 'eventStatus', 'label' => 'Status', 'type' => 'select', 'required' => true, 'options' => ['Upcoming', 'Ongoing', 'Completed', 'Cancelled']],
        ],
    ],
    'announcements' => [
        'title' => 'Announcements',
        'primaryKey' => 'announcementID',
        'columns' => ['announcementTitle' => 'Announcement', 'adminName' => 'Posted by', 'createdAt' => 'Posted'],
        'fields' => [
            ['name' => 'announcementTitle', 'label' => 'Title', 'type' => 'text', 'required' => true, 'maxlength' => 150],
            ['name' => 'announcementContent', 'label' => 'Content', 'type' => 'textarea', 'required' => true, 'full' => true],
        ],
    ],
];
