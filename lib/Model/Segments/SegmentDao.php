<?php

class Segments_SegmentDao extends DataAccess_AbstractDao {
    const TABLE = 'segments' ;
    protected static $auto_increment_fields = ['id'];

    public function countByFile( Files_FileStruct $file ) {
        $conn = $this->con->getConnection();
        $sql = "SELECT COUNT(1) FROM segments WHERE id_file = :id_file " ;

        $stmt = $conn->prepare( $sql ) ;
        $stmt->execute( array( 'id_file' => $file->id ) ) ;
        return (int) $stmt->fetch()[0] ;
    }

    /**
     * Returns an array of segments for the given file.
     * In order to limit the amount of memory, this method accepts an array of
     * columns to be returned.
     *
     * @param $id_file
     * @param $fields_list array
     *
     * @return Segments_SegmentStruct[]
     */
    public function getByFileId( $id_file, $fields_list = array() ) {
        $conn = $this->con->getConnection();

        if ( empty( $fields_list ) ) {
            $fields_list[] = '*' ;
        }

        $sql = " SELECT " . implode(', ', $fields_list ) . " FROM segments WHERE id_file = :id_file " ;
        $stmt = $conn->prepare( $sql ) ;
        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );
        $stmt->execute( array( 'id_file' => $id_file ) ) ;

        return $stmt->fetchAll() ;
    }

    /**
     * @param Chunks_ChunkStruct $chunk
     * @return mixed
     */
    function countByChunk( Chunks_ChunkStruct $chunk) {
        $conn = $this->con->getConnection();
        $query = "SELECT COUNT(1) FROM segments s
            JOIN segment_translations st ON s.id = st.id_segment
            JOIN jobs ON st.id_job = jobs.id
            WHERE jobs.id = :id_job
            AND jobs.password = :password
            AND s.show_in_cattool ;
            "  ;
        $stmt = $conn->prepare( $query ) ;
        $stmt->execute( array( 'id_job' => $chunk->id, 'password' => $chunk->password ) ) ;
        $result = $stmt->fetch() ;
        return (int) $result[ 0 ] ;
    }

    /**
     * @param $id_job
     * @param $password
     * @param $id_segment
     * @return \Segments_SegmentStruct
     */
    function getByChunkIdAndSegmentId( $id_job, $password, $id_segment) {
        $conn = $this->con->getConnection();

        $query = " SELECT segments.* FROM segments " .
                " INNER JOIN files_job fj USING (id_file) " .
                " INNER JOIN jobs ON jobs.id = fj.id_job " .
                " INNER JOIN files f ON f.id = fj.id_file " .
                " WHERE jobs.id = :id_job AND jobs.password = :password" .
                " AND segments.id_file = f.id " .
                " AND segments.id = :id_segment " ;

        $stmt = $conn->prepare( $query );

        $stmt->execute( array(
                'id_job'   => $id_job,
                'password' => $password,
                'id_segment'=> $id_segment
        ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetch();
    }

    /**
     * @param $id_job
     * @param $password
     *
     * @return Segments_SegmentStruct[]
     */
    function getByChunkId( $id_job, $password ) {
        $conn = $this->con->getConnection();

        $query = "SELECT segments.* FROM segments
                 INNER JOIN files_job fj USING (id_file)
                 INNER JOIN jobs ON jobs.id = fj.id_job
                 AND jobs.id = :id_job AND jobs.password = :password
                 INNER JOIN files f ON f.id = fj.id_file
                 WHERE jobs.id = :id_job AND jobs.password = :password
                 AND segments.id_file = f.id
                 AND segments.id BETWEEN jobs.job_first_segment AND jobs.job_last_segment
                 ";

        $stmt = $conn->prepare( $query );

        $stmt->execute( array(
                'id_job'   => $id_job,
                'password' => $password
        ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetchAll();
    }

    /**
     * @param $id_segment
     *
     * @return Segments_SegmentStruct
     */
    public function getById( $id_segment ) {
        $conn = $this->con->getConnection();

        $query = "select * from segments where id = :id";
        $stmt  = $conn->prepare( $query );
        $stmt->execute( array( 'id' => (int)$id_segment ) );

        $stmt->setFetchMode( PDO::FETCH_CLASS, 'Segments_SegmentStruct' );

        return $stmt->fetch();
    }

    /**
     * @param        $jid
     * @param        $password
     * @param int    $step
     * @param        $ref_segment
     * @param string $where
     *
     * @return array
     * @throws Exception
     */
    public function getSegmentsForQR( $jid, $password, $step = 10, $ref_segment, $where = "after", $options = [] ) {

        $db = Database::obtain()->getConnection();

        $queryAfter = "
                SELECT * FROM (
                    SELECT segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id = id_job
                    WHERE id_job = :id_job
                        AND password = :password
                        AND show_in_cattool = 1
                        AND segments.id > :ref_segment
                        AND segments.id BETWEEN job_first_segment AND job_last_segment
                    LIMIT %u
                ) AS TT1";

        $queryBefore = "
                SELECT * from(
                    SELECT  segments.id AS __sid
                    FROM segments
                    JOIN segment_translations ON id = id_segment
                    JOIN jobs ON jobs.id =  id_job
                    WHERE id_job = :id_job
                        AND password = :password
                        AND show_in_cattool = 1
                        AND segments.id < :ref_segment
                        AND segments.id BETWEEN job_first_segment AND job_last_segment
                    ORDER BY __sid DESC
                    LIMIT %u
                ) as TT2";

        /*
         * This query is an union of the last two queries with only one difference:
         * the queryAfter parts differs for the equal sign.
         * Here is needed
         *
         */
        $queryCenter = "
                  SELECT * FROM ( 
                        SELECT segments.id AS __sid
                        FROM segments
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id = id_job
                        WHERE id_job = :id_job
                            AND password = :password
                            AND show_in_cattool = 1
                            AND segments.id >= :ref_segment
                        LIMIT %u 
                  ) AS TT1
                  UNION
                  SELECT * from(
                        SELECT  segments.id AS __sid
                        FROM segments
                        JOIN segment_translations ON id = id_segment
                        JOIN jobs ON jobs.id =  id_job
                        WHERE id_job = :id_job
                            AND password = :password
                            AND show_in_cattool = 1
                            AND segments.id < :ref_segment
                        ORDER BY __sid DESC
                        LIMIT %u
                  ) AS TT2";

        switch ( $where ) {
            case 'after':
                $subQuery = sprintf( $queryAfter, $step * 2 );
                break;
            case 'before':
                $subQuery = sprintf( $queryBefore, $step * 2 );
                break;
            case 'center':
                $subQuery = sprintf( $queryCenter, $step, $step );
                break;
            default:
                throw new Exception("No direction selected");
                break;
        }

        $stmt = $db->prepare($subQuery);
        $stmt->execute( [ 'id_job' => $jid, 'password' => $password, 'ref_segment' => $ref_segment] );
        $segments_id = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $segments_id = array_map(function($segment_row){
            return $segment_row['__sid'];
        }, $segments_id);

        $prepare_str_segments_id = str_repeat( '?,', count( $segments_id ) - 1) . '?';

        $query = "SELECT 
                s.id AS sid,
                s.segment,
                s.raw_word_count,
                IF (st.status='NEW',NULL,st.translation) AS translation,
                UNIX_TIMESTAMP(st.translation_date) AS version,
                IF( st.locked AND match_type = 'ICE', 1, 0 ) AS ice_locked,
                st.status,
                COALESCE(time_to_edit, 0) AS time_to_edit,
                st.warning,
                st.suggestion_match as suggestion_match,
                st.suggestion_source,
                st.suggestion,
                st.edit_distance,
                st.locked,
                st.match_type
                FROM segments s
                JOIN segment_translations st ON st.id_segment = s.id
                
            WHERE s.id IN (" . $prepare_str_segments_id . " )
            ORDER BY sid ASC";

        $stmt = $db->prepare($query);
        $stmt->setFetchMode(PDO::FETCH_CLASS, "\QualityReport_QualityReportSegmentStruct");
        $stmt->execute( $segments_id );

        $results = $stmt->fetchAll();

        $comments_query = "SELECT * FROM comments WHERE message_type IN (1,2) AND id_segment IN (" . $prepare_str_segments_id . ")";

        $stmt = $db->prepare($comments_query);
        $stmt->setFetchMode(PDO::FETCH_CLASS, "\Comments_BaseCommentStruct");
        $stmt->execute( $segments_id );
        $comments_results = $stmt->fetchAll();

        foreach($results as $result){
            foreach ($comments_results as $comment){
                $comment->templateMessage();
                if($comment->id_segment == $result->sid){
                    $result->comments[] = $comment;
                }
            }
        }


        return $results;
    }

    /**
     * @param Segments_SegmentStruct[] $obj_arr
     *
     * @throws Exception
     */
    public function createList( Array $obj_arr ) {

        $obj_arr = array_chunk( $obj_arr, 100 );

        $baseQuery = "INSERT INTO segments ( 
                            id, 
                            internal_id, 
                            id_file,
                            /* id_project, */ 
                            segment, 
                            segment_hash, 
                            raw_word_count, 
                            xliff_mrk_id, 
                            xliff_ext_prec_tags, 
                            xliff_ext_succ_tags, 
                            show_in_cattool,
                            xliff_mrk_ext_prec_tags,
                            xliff_mrk_ext_succ_tags
                            ) VALUES ";


        Log::doLog( "Segments: Total Queries to execute: " . count( $obj_arr ) );

        $tuple_marks = "( " . rtrim( str_repeat( "?, ", 12 ), ", " ) . " )";  //set to 13 when implements id_project

        foreach ( $obj_arr as $i => $chunk ) {

            $query = $baseQuery . rtrim( str_repeat( $tuple_marks . ", ", count( $chunk ) ), ", " );

            $values = [];
            foreach( $chunk as $segStruct ){

                $values[] =$segStruct->id;
                $values[] =$segStruct->internal_id;
                $values[] =$segStruct->id_file;
                /* $values[] = $segStruct->id_project */
                $values[] = $segStruct->segment;
                $values[] = $segStruct->segment_hash;
                $values[] = $segStruct->raw_word_count;
                $values[] = $segStruct->xliff_mrk_id;
                $values[] = $segStruct->xliff_ext_prec_tags;
                $values[] = $segStruct->xliff_ext_succ_tags;
                $values[] = $segStruct->show_in_cattool;
                $values[] = $segStruct->xliff_mrk_ext_prec_tags;
                $values[] = $segStruct->xliff_mrk_ext_succ_tags;

            }

            try {

                $stm = $this->con->getConnection()->prepare( $query );
                $stm->execute( $values );
                Log::doLog( "Segments: Executed Query " . ( $i + 1 ) );

            } catch ( PDOException $e ) {
                Log::doLog( "Segment import - DB Error: " . $e->getMessage() . " - \n" );
                throw new Exception( "Segment import - DB Error: " . $e->getMessage() . " - $chunk", -2 );
            }

        }


    }

}
