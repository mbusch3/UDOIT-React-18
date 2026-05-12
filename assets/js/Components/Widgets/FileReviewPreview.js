import React, { useEffect, useState } from 'react'
import ExternalLinkIcon from '../Icons/ExternalLinkIcon'
import ContentTypeIcon from '../Icons/ContentTypeIcon'
import * as Text from '../../Services/Text'
import './FixIssuesContentPreview.css'
import FileInformation from './FileInformation'
import FileStatus from './FileStatus'
import ProgressIcon from '../Icons/ProgressIcon'
import DownwardArrowIcon from '../Icons/DownwardArrowIcon'
import SeverityPotentialIcon from '../Icons/SeverityPotentialIcon'

export default function FixIssuesContentPreview({
  t,
  settings,
  activeIssue,
  getReadableFileType,
  isDisabled
}) {

  const [fileReferenceHolder, setFileReferenceHolder] = useState([])
  const [currentFile, setCurrentFile] = useState(null)
  const [oldFile, setOldFile] = useState(null)

  useEffect(() => {
    if(activeIssue){
      handleFileReference()
      if(activeIssue.fileData.replacement){
        const tempCurrFile = {
          fileName: activeIssue.fileData.replacement.fileName,
          fileType: getReadableFileType(activeIssue.fileData.replacement.fileType),
          fileIconType: activeIssue.fileData.replacement.fileType,
          fileSize: Text.getReadableFileSize(activeIssue.fileData.replacement.fileSize),
          fileLink: activeIssue.fileData.replacement.lmsUrl
        }  
        setCurrentFile(tempCurrFile)
      }
      const tempCurrFile = {
        fileName: activeIssue.fileData.fileName,
        fileType: getReadableFileType(activeIssue.fileData.fileType),
        fileIconType: activeIssue.fileData.fileType,
        fileSize: Text.getReadableFileSize(activeIssue.fileData.fileSize),
        fileLink: activeIssue.fileData.lmsUrl
      }
      if(!activeIssue.fileData.replacement){
        setCurrentFile(tempCurrFile)
      }
      setOldFile(tempCurrFile)
      
    }
  }, [activeIssue])

  const handleFileReference = () => {
    let tempReferences = []
    let replacementRefsCount = {}
    let originalRefsCount = {}

    const compileReferenceCounts = (countObject, list) => {
      if(!list) return countObject

      list.forEach((ref) => {
        let tempRef = JSON.parse(JSON.stringify(ref))
        if(countObject[ref.contentItemId]){
          countObject[ref.contentItemId] += 1
          tempReferences.map((reference) => {
          if(reference.contentItemId == ref.contentItemId){
            reference.count = countObject[ref.contentItemId]
          }
            return reference
          })
        } else {
          countObject[ref.contentItemId] = 1
          tempRef.count = 1
          tempReferences.push(tempRef)
        }
      })
      return countObject
    }

    originalRefsCount = compileReferenceCounts(originalRefsCount, activeIssue?.fileData?.references)
    originalRefsCount = compileReferenceCounts(originalRefsCount, activeIssue?.fileData?.sectionRefs)
    replacementRefsCount = compileReferenceCounts(replacementRefsCount, activeIssue?.fileData?.replacement?.references)
    replacementRefsCount = compileReferenceCounts(replacementRefsCount, activeIssue?.fileData?.replacement?.sectionRefs)

    // activeIssue.fileData.replacement?.references?.forEach((ref) => {
    //   let tempRef = JSON.parse(JSON.stringify(ref))

    //   if(replacementRefsCount[ref.contentItemId]){
    //     replacementRefsCount[ref.contentItemId] += 1
    //     tempReferences.map((reference) => {
    //       if(reference.contentItemId == ref.contentItemId){
    //         reference.count = replacementRefsCount[ref.contentItemId]
    //       }
    //       return reference
    //     })
    //   } else {
    //     replacementRefsCount[ref.contentItemId] = 1
    //     tempRef.status  = 1
    //     tempRef.count = 1
    //     tempReferences.push(tempRef)
    //   }
    // })

    // activeIssue.fileData.replacement?.sectionRefs?.forEach((ref) => {
    //   let tempRef = JSON.parse(JSON.stringify(ref))

    //   if(replacementRefsCount[ref.contentItemId]){
    //     replacementRefsCount[ref.contentItemId] += 1
    //     tempReferences.map((reference) => {
    //       if(reference.contentItemId == ref.contentItemId){
    //         reference.count = replacementRefsCount[ref.contentItemId]
    //       }
    //       return reference
    //     })
    //   } else {
    //     replacementRefsCount[ref.contentItemId] = 1
    //     tempRef.status  = 1
    //     tempRef.count = 1
    //     tempReferences.push(tempRef)
    //   }
    // })


    // activeIssue.fileData.references?.forEach((ref) => {
    //   let tempRef = JSON.parse(JSON.stringify(ref))

    //   if(originalRefsCount[ref.contentItemId]){
    //     originalRefsCount[ref.contentItemId] += 1
    //     tempReferences.map((reference) => {
    //       if(reference.contentItemId == ref.contentItemId){
    //         reference.count = originalRefsCount[ref.contentItemId]
    //       }
    //       return reference
    //     })
    //   } else {
    //     originalRefsCount[ref.contentItemId] = 1
    //     tempRef.status  = 0
    //     tempRef.count = 1
    //     tempReferences.push(tempRef)
    //   }
    // })


    // activeIssue.fileData.sectionRefs?.forEach((ref) => {
    //   let tempRef = JSON.parse(JSON.stringify(ref))

    //   if(originalRefsCount[ref.contentItemId]){
    //     originalRefsCount[ref.contentItemId] += 1
    //     tempReferences.map((reference) => {
    //       if(reference.contentItemId == ref.contentItemId){
    //         reference.count = originalRefsCount[ref.contentItemId]
    //       }
    //       return reference
    //     })
    //   } else {
    //     originalRefsCount[ref.contentItemId] = 1
    //     tempRef.status  = 0
    //     tempRef.count = 1
    //     tempReferences.push(tempRef)
    //   }
    // })
    
    setFileReferenceHolder(tempReferences)
  }
    



  return (
    <>
      { isDisabled ? (
        <div className="flex-column h-100 flex-grow-1 justify-content-center">
          <div className="flex-row justify-content-center align-items-center mb-4">
            <ProgressIcon className="icon-lg udoit-progress spinner" />
            <h2 className="mt-0 mb-0 ps-3">{t('fix.label.loading_content')}</h2>
          </div>
        </div> 
      ) : (
        <>
          { activeIssue.fileData.replacement ? (
            <>
              <div className='file-label-pill'>{t('form.file.original.label')}</div>
              <div className='callout-container w-100 mt-2'>
                <FileInformation t={t} file={oldFile} />
              </div>
              <div className="flex-row w-100 justify-content-center mt-2">
                <DownwardArrowIcon className="icon-md gray" />
              </div>
              <div className='file-label-pill file-new'>{t('form.file.new.label')}</div>
              <div className='callout-container w-100 mt-2'>
                <FileInformation t={t} file={currentFile} />
              </div>
            </>
          ) : ( currentFile && ( 
            <>
              <div className="flex-row gap-2 align-items-center">
                <div className="strong-caps">{t('form.file.current.label')}</div>
                { activeIssue.fileData.replacement ? (
                  <div className='file-label-pill file-new'>{t('form.file.new.label')}</div>
                ) : (
                  <div className='file-label-pill'>{t('form.file.original.label')}</div>
                )}
              </div>
              <div className='callout-container w-100 mt-2'>
                <FileInformation t={t} file={currentFile} />
              </div>
            </>
          ))}

          { fileReferenceHolder.length > 0 ? (
            <>
              <div className="strong-caps mt-3">{t('form.file.instances.label')}</div>
              <div className="mt-2 rounded-table-wrapper">
                <table className="udoit-sortable-table first-column-wide">
                  <thead>
                    <tr>
                      <th>{t('form.file.location.label')}</th>
                      <th>{t('fix.label.references')}</th>
                      <th>{t('form.file.status.label')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    { fileReferenceHolder?.map((ref, index) => (
                      <tr key={index}>
                        <td>
                          <a href={ref.contentType == "quiz_question" ? ref.contentItemUrl.replace(/\/questions.*/, "/edit#questions_tab") : ref.contentItemUrl} target='_blank' className='location-link flex-row align-items-center'>
                            {ref.contentItemTitle}
                            <ExternalLinkIcon className="link-color align-self-center ms-2 icon-sm"/>
                          </a>
                        </td>
                        <td>{ref.count}</td>
                        <td>
                          {activeIssue.fileData.replacement ? (
                            <div className='file-label-pill file-new'>{t('form.file.new.label')}</div>
                          ) : (
                            <div className='file-label-pill'>{t('form.file.original.label')}</div>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          ) : (
            <div className="option-feedback feedback-warning mt-3">
              <SeverityPotentialIcon className="icon-md udoit-potential-highlight align-self-top pe-2"/>
              <div>{t('form.file.no_ref.label')}</div>
            </div>
          )}
        </>
      )}
    </>
  )
}