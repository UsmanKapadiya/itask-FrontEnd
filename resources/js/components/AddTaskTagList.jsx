import React, { useState, useEffect } from 'react';
import { Select } from 'antd';

const { Option } = Select;

const Taglist = (props) => {
    const [popoverOpen, setPopoverOpen] = useState(false);
    const [tags, setTags] = useState([]);
    const [selectedTags, setSelectedTags] = useState(props.selectedTags || []);

    useEffect(() => {
        fetch("/member-tags")
            .then((res) => res.json())
            .then(
                (result) => {
                    setTags(result);
                },
                (error) => {
                    console.error("Error fetching tags:", error);
                }
            );
    }, []);

    useEffect(() => {
        if (props.selectedTags && props.selectedTags !== selectedTags) {
            setSelectedTags(props.selectedTags);
        }
    }, [props.selectedTags]);

    const toggle = () => {
        setPopoverOpen(!popoverOpen);
    };

    const handleChange = (value) => {
        props.tagsCallback(value);
        setSelectedTags(value);
    };

    const handleBlur = () => {
        setPopoverOpen(false);
        props.tagCreation('tag');
    };

    return (
        <div className="mr-3 position-relative cursor_pointer" id="tags_container">
            <a onClick={toggle}>
                <i className="fas fa-tag" data-toggle="tooltip" title="Add label(s)"></i>
            </a>
            {popoverOpen && (
                <div className="flag-selection-outer">
                    <Select
                        style={{ width: '100%' }}
                        mode="tags"
                        placeholder="Select tag"
                        value={selectedTags.length === 0 ? [] : selectedTags}
                        onChange={handleChange}
                        optionLabelProp="label"
                        defaultOpen={true}
                        menuItemSelectedIcon={<i className="fas fa-check"></i>}
                        getPopupContainer={() =>
                            document.getElementsByClassName('modal')[0] || document.getElementById('mainapp')
                        }
                        removeIcon={<i className="fas fa-times remove-member"></i>}
                        autoFocus={true}
                        onBlur={handleBlur}
                    >
                        {tags.map((individual) => (
                            <Option key={individual.id} value={individual.name} label={individual.name}>
                                <div className="demo-option-label-item">{individual.name}</div>
                            </Option>
                        ))}
                    </Select>
                </div>
            )}
        </div>
    );
};

export default Taglist;