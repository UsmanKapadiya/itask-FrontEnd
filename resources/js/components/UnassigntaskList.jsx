import React, { useState, useEffect, useContext, useRef } from 'react';
import { message, Popover } from "antd";
import dotImage from "../../../public/images/icon_left_more.png";
import ProjectContext from "./projectContext";
import Nestable from "react-nestable";
import iconDropLeft from "../../../public/images/icon_left_dropleft.png";
import iconDropDown from "../../../public/images/icon_left_dropdown.png";
import { reorderData } from "./FunctionCalls";
import $ from 'jquery';

const UnassigntaskList = (props) => {
    const [data, setData] = useState([]);
    const [completed, setCompleted] = useState(0);
    const popoverRef = useRef(null);
    const context = useContext(ProjectContext);

    useEffect(() => {
        getData();
    }, []);

    useEffect(() => {
        if (context.toUpdate || context.showCompleted !== "") {
            getData();
        }
    }, [context.toUpdate, context.showCompleted]);

    const getData = () => {
        // fetch("/unassign-task", {
        //     method: 'POST',
        //     headers: {
        //         'Accept': 'application/json',
        //         'Content-Type': 'application/json',
        //         'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),

        //     },
        //     body: JSON.stringify({
        //         "completed": (context.showCompleted !== '' ? context.showCompleted : completed)
        //     })
        // })
        fetch("/unassign-task", {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': `TOPEkhHmw5zZgpuhmhVahdizlWg7UqqNjSpXJsS1`,//$('meta[name="csrf-token"]').attr('content'),
            },
            credentials: 'include',
            body: JSON.stringify({
                "completed": (context.showCompleted !== '' ? context.showCompleted : completed)
            })
        })
            .then(res => res.json())
            .then(
                (result) => {
                    setData(result);
                    props.updateInboxCount();
                },
                (error) => {
                    console.error(error);
                }
            );

        if (context.toUpdate) {
            context.setUpdate(0);
        }
        if (context.showCompleted !== "") {
            setCompleted(1);
            context.updateCompletedState('');
        }
    };

    const completedTask = () => {
        setCompleted(1);
        getData();
        popoverRef.current.props.onPopupVisibleChange(false);
    };

    const hideCompletedTask = () => {
        setCompleted(0);
        getData();
        popoverRef.current.props.onPopupVisibleChange(false);
    };

    const reorderDataHandler = (data, project_id) => {
        let new_data = [];
        for (let d in data) {
            getRecursiveData(data[d], new_data, 0);
        }
        reorderData({ project_id: project_id, base_parent_id: 0, reorder_data: new_data }).then(res => {
            if (res.errorStatus) {
                message.error(res.data);
            } else {
                message.success(res.data, 3);
            }
        });
    };

    const getRecursiveData = (data, result, is_inner) => {
        let childs = [];
        if (data.children.length > 0) {
            for (let i in data.children) {
                childs[i] = getRecursiveData(data.children[i], [], 1);
            }
        }
        if (!is_inner) {
            result.push({
                id: data.id,
                childs: childs
            });
            return result;
        } else {
            return {
                id: data.id,
                childs: childs
            };
        }
    };

    return (
        <div className={"container can_drag"} style={{height:'100vh'}}>
            <div className={"bg-white h-100 p-5"}>
                <div className={'float-left'}>
                    <h2 className={"font-weight-bold text-left color-navyblue"}>Inbox</h2>
                </div>
                <div className={'float-right'}>
                    <Popover ref={popoverRef} content={(
                        <div>
                            {!completed ? (
                                <div className={'d-flex align-items-center cursor_pointer'} onClick={completedTask}>
                                    Show completed tasks
                                </div>
                            ) : (
                                <div className={'d-flex align-items-center cursor_pointer'} onClick={hideCompletedTask}>
                                    Hide completed tasks
                                </div>
                            )}
                        </div>
                    )} trigger="click" placement="bottomLeft">
                        <span className={"cursor_pointer"}>
                            <img src={dotImage} alt={"Options"} />
                        </span>
                    </Popover>
                </div>
                <div className={"clearfix"}></div>
                <div>
                    {data.length > 0 ? (
                        <Nestable
                            items={data}
                            collapsed={true}
                            renderItem={({ item, collapseIcon }) => (
                                <>
                                    {/* <SingleRowList key={item.id} data={item} flag={true} review={false}
                                        id={item.id}
                                        collapseIcon={collapseIcon}
                                        updateSelection={() => {
                                        }}/> */}
                                </>
                            )}
                            renderCollapseIcon={({ isCollapsed }) =>
                                isCollapsed ? (
                                    <img src={iconDropLeft} alt="Open" className="cursor_pointer" />
                                ) : (
                                    <img src={iconDropDown} alt="Close" className="cursor_pointer" />
                                )
                            }
                            onChange={(items, item) => {
                                reorderDataHandler(items, item.id);
                            }}
                        />
                    ) : <h3 className={"text-center"}>No task(s) found</h3>}
                </div>
            </div>
        </div>
    );
};

export default UnassigntaskList;