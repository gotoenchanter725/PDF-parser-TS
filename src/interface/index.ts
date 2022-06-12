interface RestFile extends Response {
    data: string
}

interface FileInterface {
    fieldname: string,
    originalname: string,
    encoding: string,
    mimetype: string,
    destination: string,
    filename: string,
    path: string,
    size: number
}

interface dataConvertType {
    flag: string,
    extension: string
}
interface FormatsType {
    fdx: dataConvertType,
    json: dataConvertType
}

export { RestFile, FileInterface, dataConvertType, FormatsType };