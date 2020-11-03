import React, {Component} from 'react';
import TooltipInfo from "../TooltipInfo/TooltipInfo.component";
import {tagSignatures, getTooltipTag} from "../utils/DraftMatecatUtils/tagModel";

class TagEntity extends Component {
    constructor(props) {
        super(props);

        const tagStyle = this.selectCorrectStyle();
        this.state = {
            selected: false,
            selectionStateInputs: {
                anchorOffset: null,
                focusOffset: null,
                anchorKey: '',
                focusKey: '',
            },
            tagWarningStyle: '',
            showTooltip: false,
            tagFocusedStyle: '',
            tagStyle,
        };
        this.warningCheck;
        this.startChecks = this.startChecks.bind(this);
    }

    tooltipToggle = (show = false) => {
        // this will trigger a rerender in the main Editor Component
        this.setState({
            showTooltip: show,
        });
    };

    markSearch = (text, searchParams) => {
        let { active, currentActive, textToReplace, params, occurrences, currentInSearchIndex } = searchParams;
        let currentOccurrence = _.find(occurrences, (occ) => occ.searchProgressiveIndex === currentInSearchIndex);
        let isCurrent =
            currentOccurrence &&
            currentOccurrence.matchPosition >= this.props.start &&
            currentOccurrence.matchPosition < this.props.end;
        if (active) {
            let regex = SearchUtils.getSearchRegExp(textToReplace, params.ingnoreCase, params.exactMatch);
            var parts = text.split(regex);
            for (var i = 1; i < parts.length; i += 2) {
                if (currentActive && isCurrent) {
                    parts[i] = (
                        <span key={i} style={{ backgroundColor: 'rgb(255 210 14)' }}>
                            {parts[i]}
                        </span>
                    );
                } else {
                    parts[i] = (
                        <span key={i} style={{ backgroundColor: 'rgb(255 255 0)' }}>
                            {parts[i]}
                        </span>
                    );
                }
            }
            return parts;
        }
        return text;
    };

    startChecks() {
        if (!this.warningCheck) {
            this.warningCheck = setInterval(() => {
                this.updateTagStyle();
            }, 500);
        }
    }

    componentDidMount() {
        this.startChecks();
    }

    componentWillUnmount() {
        clearInterval(this.warningCheck);
    }

    render() {
        let searchParams = this.props.getSearchParams();
        const { selected, tagStyle, tagWarningStyle } = this.state;
        const {
            entityKey,
            offsetkey,
            blockKey,
            start,
            end,
            onClick,
            contentState,
            getUpdatedSegmentInfo,
            getClickedTagInfo,
            isTarget,
        } = this.props;
        const { currentSelection } = getUpdatedSegmentInfo();
        const { tagClickedInSource, clickedTagId, clickedTagText } = getClickedTagInfo();
        const { anchorOffset, focusOffset, anchorKey, hasFocus } = currentSelection;
        const { children } = this.props.children.props;
        const { selection, forceSelection } = children[0];
        const { tooltipToggle } = this;

        //const entity = contentState.getEntity(entityKey);
        const {
            type: entityType,
            data: { id: entityId, placeholder: entityPlaceholder, name: entityName },
        } = contentState.getEntity(entityKey);

        // Apply style on clicked tag and draggable tag, placed here for performance
        const tagFocusedStyle =
            anchorOffset === start &&
            focusOffset === end &&
            anchorKey === blockKey &&
            ((tagClickedInSource && !isTarget) || (!tagClickedInSource && isTarget)) &&
            hasFocus
                ? 'tag-focused'
                : '';
        const tagClickedStyle =
            entityId &&
            clickedTagId &&
            clickedTagId === entityId &&
            clickedTagText &&
            clickedTagText === entityPlaceholder
                ? 'tag-clicked'
                : '';

        // show tooltip only on configured tag
        const showTooltip = this.state.showTooltip && getTooltipTag().includes(entityName);
        // show tooltip only if text too long
        const textSpanDisplayed = this.tagRef && this.tagRef.querySelector('span[data-text="true"]');
        const shouldTooltipOnHover = textSpanDisplayed && textSpanDisplayed.offsetWidth < textSpanDisplayed.scrollWidth;

        if (searchParams.active) {
            let text = this.markSearch(children[0].props.text, searchParams);
            return (
                <div
                    className={'tag-container'}
                    ref={(ref) => (this.tagRef = ref)}
                    /*contentEditable="false"
                suppressContentEditableWarning={true}*/
                >
                    {showTooltip && <TooltipInfo text={entityPlaceholder} isTag tagStyle={tagStyle} />}
                    <span
                        data-offset-key={offsetkey}
                        className={`tag ${tagStyle} ${tagWarningStyle} ${tagClickedStyle} ${tagFocusedStyle}`}
                        unselectable="on"
                        suppressContentEditableWarning={true}
                        onMouseEnter={() => tooltipToggle(shouldTooltipOnHover)}
                        onMouseLeave={() => tooltipToggle()}
                        /*contentEditable="false"*/
                        onClick={() => onClick(start, end, entityId, entityPlaceholder)}
                    >
                        {text}
                    </span>
                    <span style={{ display: 'none' }}>{children}</span>
                </div>
            );
        } else {
            return (
                <div
                    className={'tag-container'}
                    ref={(ref) => (this.tagRef = ref)}
                    /*contentEditable="false"
                        suppressContentEditableWarning={true}*/
                >
                    {showTooltip && <TooltipInfo text={entityPlaceholder} isTag tagStyle={tagStyle} />}
                    <span
                        data-offset-key={offsetkey}
                        className={`tag ${tagStyle} ${tagWarningStyle} ${tagClickedStyle} ${tagFocusedStyle}`}
                        unselectable="on"
                        suppressContentEditableWarning={true}
                        onMouseEnter={() => tooltipToggle(shouldTooltipOnHover)}
                        onMouseLeave={() => tooltipToggle()}
                        /*contentEditable="false"*/
                        onClick={(e) => {
                            e.stopPropagation();
                            onClick(start, end, entityId, entityPlaceholder);
                        }}
                    >
                        {children}
                    </span>
                </div>
            );
        }
    }

    updateTagStyle = () => {
        this.setState({
            tagStyle: this.selectCorrectStyle(),
            tagWarningStyle: this.highlightOnWarnings(),
        });
    };

    selectCorrectStyle = () => {
        const {
            entityKey,
            contentState,
            getUpdatedSegmentInfo,
            isRTL,
            isTarget,
            start,
            end,
            getClickedTagInfo,
        } = this.props;
        const entityInstance = contentState.getEntity(entityKey);
        const { segmentOpened, currentSelection } = getUpdatedSegmentInfo();
        const { tagClickedInSource } = getClickedTagInfo();
        const { anchorOffset, focusOffset, hasFocus } = currentSelection;
        let tagStyle = [];

        // Apply style on clicked tag and draggable tag, placed here for performance
        anchorOffset <= start &&
            focusOffset >= end &&
            ((tagClickedInSource && !isTarget) || (!tagClickedInSource && isTarget)) &&
            hasFocus &&
            tagStyle.push('tag-focused');

        // Check for tag type
        const entityType = entityInstance.type;
        const entityName = entityInstance.data.name;
        const style =
            isRTL && tagSignatures[entityName].styleRTL
                ? tagSignatures[entityName].styleRTL
                : tagSignatures[entityName].style;
        tagStyle.push(style);
        // Check if tag is in an active segment
        if (!segmentOpened) tagStyle.push('tag-inactive');
        return tagStyle.join(' ');
    };

    highlightOnWarnings = () => {
        const { getUpdatedSegmentInfo, contentState, entityKey, isTarget } = this.props;
        const { warnings, tagMismatch, tagRange, segmentOpened, missingTagsInTarget } = getUpdatedSegmentInfo();
        //const draftEntity = contentState.getEntity(entityKey);
        const { type: entityType, data: entityData } = contentState.getEntity(entityKey) || {};
        const { id: entityId, encodedText, openTagId, closeTagId } = entityData || {};

        if (!segmentOpened || !tagMismatch) return;
        let tagWarningStyle = '';
        if (tagMismatch.target && tagMismatch.target.length > 0 && isTarget) {
            let tagObject;
            // Todo: Check tag type and tag id instead of string
            tagMismatch.target.forEach((tagString) => {
                // build tag from string
                //tagObject = DraftMatecatUtils.tagFromString(tagString);
                /*if(entityType === tagObject.type){
                    // If tag is closure and openTagId/closeTagId are null, then the tag was added after initial rendering
                    if(getClosureTag().includes(tagObject.type)){
                        /!*if(!entityData.openTagId && !entityData.closeTagId){
                            tagWarningStyle = 'tag-mismatch-error'
                        }*!/
                        tagWarningStyle = 'tag-mismatch-error'
                    }else if(entityData.id === tagObject.data.id){
                        tagWarningStyle = 'tag-mismatch-error'
                    }
                }*/
                if (entityData.encodedText === tagString) {
                    tagWarningStyle = 'tag-mismatch-error';
                }
            });
        } else if (tagMismatch.source && tagMismatch.source.length > 0 && !isTarget && missingTagsInTarget) {
            // Find tag and specific Tag ID in missing tags in target array
            const missingTagInError = missingTagsInTarget.filter((tag) => {
                return tag.data.encodedText === encodedText && tag.data.id === entityId;
            });
            // Array should contain only one item
            if (missingTagInError.length === 1) tagWarningStyle = 'tag-mismatch-error';
            /*tagMismatch.source.forEach(tagString => {
                if(entityData.encodedText === tagString){
                    tagWarningStyle = 'tag-mismatch-error'
                }
            });*/
        } else if (tagMismatch.order && isTarget) {
            tagMismatch.order.forEach((tagString) => {
                if (entityData.encodedText === tagString) {
                    tagWarningStyle = 'tag-mismatch-warning';
                }
            });
        } /*else if(entityData.id){
            tagWarningStyle = 'tag-mismatch-error'
        }*/

        return tagWarningStyle;
    };
}


export default TagEntity;
